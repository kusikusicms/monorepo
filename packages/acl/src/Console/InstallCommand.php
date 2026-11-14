<?php

namespace KusikusiCMS\Acl\Console;

use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use KusikusiCMS\Acl\Exceptions\InvalidEntityPermissionException;
use KusikusiCMS\Acl\Support\EntityPermission;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionServiceProvider;
use Spatie\Permission\Traits\HasRoles;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kusikusicms:acl:install
        {--guard= : The auth guard of the permissions (default "web")}
        {--user-id-type= : The type of the user ids: int, uuid or ulid (default "int")}
        {--guest-role= : The name of the role applied to everyone ("none" to disable, default "guest")}
        {--guest-permissions= : The guest role permissions separated by "|" (default "entities.view.*.*")}
        {--event-guard= : Enable the event guard that blocks unauthorized writes: yes or no (default "no")}
        {--force : Overwrite the published ACL config}';

    protected $aliases = ['k:acl:install'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Configure Spatie Laravel Permission and the KusikusiCMS ACL package';

    public const DEFAULT_GUEST_PERMISSIONS = 'entities.view.*.*';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $guard = $this->textValue('guard', 'Which auth guard do the permissions use?', 'web');
        $userIdType = $this->choiceValue('user-id-type', 'Which type are the ids of your users?', [
            'int' => 'Integer (auto increment)',
            'uuid' => 'UUID',
            'ulid' => 'ULID',
        ], 'int');
        $guestRole = $this->guestRole();
        $guestPermissions = $guestRole === null ? [] : $this->guestPermissions();
        $eventGuard = $this->booleanValue('event-guard', 'Enable the event guard? It blocks unauthorized entity writes during HTTP requests.', false);

        if ($guard === '' || $userIdType === null || $guestPermissions === null || $eventGuard === null) {
            return self::FAILURE;
        }

        $this->publishPermissionConfig();
        $this->publishPermissionMigration($userIdType);
        $this->write(config_path('kusikusicms/acl.php'), $this->config($guard, $guestRole, $eventGuard));

        config([
            'permission.enable_wildcard_permission' => true,
            'kusikusicms.acl.guard_name' => $guard,
            'kusikusicms.acl.guest_role' => $guestRole,
            'kusikusicms.acl.guard.enabled' => $eventGuard,
        ]);

        if ($guestRole !== null) {
            $this->createGuestRole($guestRole, $guestPermissions, $guard);
        }

        $this->checkUserModel();
        $this->components->info('KusikusiCMS ACL installed. Run "php artisan migrate" if the permission tables do not exist yet.');

        return self::SUCCESS;
    }

    /**
     * The ACL package config with the answers filled in.
     *
     * @throws RuntimeException When the package config no longer has the expected entries
     */
    public function config(string $guard, ?string $guestRole, bool $eventGuard): string
    {
        $replacements = [
            '/^(\s*)\'guard_name\' => .*,$/m' => '${1}\'guard_name\' => '.var_export($guard, true).',',
            '/^(\s*)\'guest_role\' => .*,$/m' => '${1}\'guest_role\' => '.($guestRole === null ? 'null' : var_export($guestRole, true)).',',
            '/^(\s*)\'enabled\' => env\(\'KUSIKUSICMS_ACL_GUARD\'.*,$/m' => '${1}\'enabled\' => env(\'KUSIKUSICMS_ACL_GUARD\', '.($eventGuard ? 'true' : 'false').'),',
        ];

        $config = (string) File::get(dirname(__DIR__, 2).'/config/acl.php');
        foreach ($replacements as $pattern => $replacement) {
            $config = (string) preg_replace($pattern, str_replace('\\', '\\\\', $replacement), $config, 1, $count);
            if ($count !== 1) {
                throw new RuntimeException("The acl config template does not match [{$pattern}].");
            }
        }

        return $config;
    }

    /**
     * The directory of the installed spatie/laravel-permission package.
     */
    public static function spatiePath(string $path = ''): string
    {
        $provider = (new \ReflectionClass(PermissionServiceProvider::class))->getFileName();
        if ($provider === false) {
            throw new RuntimeException('Unable to locate spatie/laravel-permission.');
        }

        return dirname($provider, 2).($path === '' ? '' : '/'.$path);
    }

    /**
     * Publish config/permission.php when missing, and turn wildcard permissions on.
     */
    protected function publishPermissionConfig(): void
    {
        $path = config_path('permission.php');

        if (! File::exists($path)) {
            File::put($path, (string) File::get(static::spatiePath('config/permission.php')));
            $this->components->info("Created: {$path}");
        }

        $contents = (string) File::get($path);
        $updated = (string) preg_replace("/'enable_wildcard_permission' => false,/", "'enable_wildcard_permission' => true,", $contents, 1, $count);

        if ($count === 1) {
            File::put($path, $updated);
            $this->components->info('Enabled wildcard permissions in config/permission.php.');
        } elseif (! str_contains($contents, "'enable_wildcard_permission' => true")) {
            $this->components->warn('Set "enable_wildcard_permission" to true in config/permission.php, the ACL needs it.');
        }
    }

    /**
     * Publish the permission tables migration when missing, and adjust the user id column type.
     */
    protected function publishPermissionMigration(string $userIdType): void
    {
        $existing = File::glob(database_path('migrations/*_create_permission_tables.php'));
        $path = $existing[0] ?? database_path('migrations/'.date('Y_m_d_His').'_create_permission_tables.php');

        if ($existing === []) {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, (string) File::get(static::spatiePath('database/migrations/create_permission_tables.php.stub')));
            $this->components->info("Created: {$path}");
        }

        if ($userIdType === 'int') {
            return;
        }

        $contents = (string) File::get($path);
        $updated = str_replace(
            "unsignedBigInteger(\$columnNames['model_morph_key'])",
            "{$userIdType}(\$columnNames['model_morph_key'])",
            $contents,
            $count,
        );

        if ($count > 0) {
            File::put($path, $updated);
            $this->components->info("Changed the user id columns of the permission tables to {$userIdType}.");
        }
    }

    /**
     * Create the guest role with its permissions when the permission tables exist.
     *
     * @param  list<string>  $permissions
     */
    protected function createGuestRole(string $roleName, array $permissions, string $guard): void
    {
        try {
            if (! Schema::hasTable((string) config('permission.table_names.roles', 'roles'))) {
                $this->components->warn("Run the migrations and this command again to create the [{$roleName}] role.");

                return;
            }

            $role = Role::findOrCreate($roleName, $guard);
            foreach ($permissions as $name) {
                $role->givePermissionTo(Permission::findOrCreate($name, $guard));
            }
            $this->components->info("Role [{$roleName}] has: ".($permissions === [] ? 'no permissions' : implode(', ', $permissions)));
        } catch (QueryException) {
            $this->components->warn("Could not create the [{$roleName}] role (run the migrations first).");
        }
    }

    /**
     * Warn when the user model cannot have roles and permissions.
     */
    protected function checkUserModel(): void
    {
        $model = config('auth.providers.users.model');

        if (is_string($model) && class_exists($model) && in_array(HasRoles::class, class_uses_recursive($model), true)) {
            return;
        }

        $this->components->warn('Add the Spatie\Permission\Traits\HasRoles trait to your user model. Until then users only get the guest permissions.');
    }

    /**
     * The guest role name, or null when it is disabled.
     */
    protected function guestRole(): ?string
    {
        $option = $this->option('guest-role');
        if (is_string($option)) {
            $option = trim($option);

            return $option === '' || $option === 'none' ? null : $option;
        }
        if (! $this->input->isInteractive()) {
            return 'guest';
        }
        if (! confirm(label: 'Create a guest role whose permissions apply to everyone (visitors and users)?', default: true)) {
            return null;
        }

        return trim(text(label: 'What is the name of the guest role?', default: 'guest', required: true));
    }

    /**
     * The guest permissions, or null (after an error) when one of them is invalid.
     *
     * @return list<string>|null
     */
    protected function guestPermissions(): ?array
    {
        $value = $this->textValue('guest-permissions', 'Which permissions does the guest role have? (separated by "|")', self::DEFAULT_GUEST_PERMISSIONS, false);
        $permissions = array_values(array_filter(array_map('trim', explode('|', $value)), fn (string $name) => $name !== ''));

        foreach ($permissions as $name) {
            try {
                EntityPermission::parse($name);
            } catch (InvalidEntityPermissionException $exception) {
                $this->components->error($exception->getMessage());

                return null;
            }
        }

        return $permissions;
    }

    /**
     * An option value, or the answer to a text prompt (the default when not interactive).
     */
    protected function textValue(string $option, string $label, string $default, bool $required = true): string
    {
        $value = $this->option($option);
        if (is_string($value)) {
            return trim($value);
        }
        if (! $this->input->isInteractive()) {
            return $default;
        }

        return trim(text(label: $label, default: $default, required: $required));
    }

    /**
     * An option value, or the answer to a select prompt. Null (after an error) for an unknown value.
     *
     * @param  array<string, string>  $options
     */
    protected function choiceValue(string $option, string $label, array $options, string $default): ?string
    {
        $value = $this->option($option);
        if (! is_string($value)) {
            $value = $this->input->isInteractive()
                ? (string) select(label: $label, options: $options, default: $default)
                : $default;
        }

        if (! array_key_exists($value, $options)) {
            $this->components->error("Invalid --{$option} [{$value}], use one of: ".implode(', ', array_keys($options)).'.');

            return null;
        }

        return $value;
    }

    /**
     * A yes/no option value, or the answer to a confirm prompt. Null (after an error) for an unknown value.
     */
    protected function booleanValue(string $option, string $label, bool $default): ?bool
    {
        $value = $this->option($option);
        if (! is_string($value)) {
            return $this->input->isInteractive() ? confirm(label: $label, default: $default) : $default;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($parsed === null) {
            $this->components->error("Invalid --{$option} [{$value}], use yes or no.");
        }

        return $parsed;
    }

    /**
     * Write a file unless it exists and --force is not given.
     */
    protected function write(string $path, string $contents): void
    {
        if (File::exists($path) && ! $this->option('force')) {
            $this->components->warn("Skipped (already exists, use --force to overwrite): {$path}");

            return;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);
        $this->components->info("Created: {$path}");
    }
}
