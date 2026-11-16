<?php

namespace KusikusiCMS\Acl\Console;

use Illuminate\Console\Command;
use KusikusiCMS\Acl\Enums\EntityAction;
use KusikusiCMS\Acl\Enums\EntityScope;
use KusikusiCMS\Acl\Exceptions\InvalidEntityPermissionException;
use KusikusiCMS\Acl\Support\EntityPermission;
use KusikusiCMS\Models\Entity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\text;

class CreatePermissionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kusikusicms:acl:create-permission
        {--action=* : The actions: view, create, update, delete or *}
        {--entity=* : The entity ids or * (default *)}
        {--scope=* : The scopes: self, children, descendants or * (default *)}
        {--role= : Attach the permission to this role (created when missing)}
        {--guard= : The auth guard of the permission (default from kusikusicms.acl.guard_name)}';

    protected $aliases = ['k:acl:create-permission'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an entity permission and optionally attach it to a role';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $actions = $this->listValue('action', 'Which actions does the permission allow?', ['*' => 'All actions'] + $this->enumOptions(EntityAction::cases()), null);
        if ($actions === []) {
            $this->components->error('At least one --action is required.');

            return self::FAILURE;
        }

        $entityIds = $this->entityIds();
        $scopes = $this->listValue('scope', 'Which entities, relative to the entity ids?', ['*' => 'All scopes'] + $this->enumOptions(EntityScope::cases()), '*');

        try {
            $permission = EntityPermission::make($actions, $entityIds, $scopes);
        } catch (InvalidEntityPermissionException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $missing = $this->missingEntities($permission->entityIds);
        if ($missing !== []) {
            $this->components->error('Unknown entity ids (they are case-sensitive): '.implode(', ', $missing));

            return self::FAILURE;
        }

        if (in_array(EntityAction::Create->value, $permission->actions, true) && $permission->scopes === [EntityScope::Self->value]) {
            $this->components->warn('"create" with only the "self" scope grants nothing: create is checked on the parent, use "children" or "descendants".');
        }

        $guard = $this->guardName();
        $model = Permission::findOrCreate($permission->name(), $guard);
        $this->components->info("Permission [{$permission->name()}] is ready for the [{$guard}] guard.");

        $role = $this->roleName();
        if ($role !== null) {
            Role::findOrCreate($role, $guard)->givePermissionTo($model);
            $this->components->info("Attached to the role [{$role}].");
        }

        return self::SUCCESS;
    }

    /**
     * The entity ids from the option or a prompt; "*" by default.
     *
     * @return list<string>
     */
    protected function entityIds(): array
    {
        $ids = $this->split($this->option('entity'));
        if ($ids !== []) {
            return $ids;
        }
        if (! $this->input->isInteractive()) {
            return ['*'];
        }

        return $this->split([text(
            label: 'Which entity ids? (separated by commas, * for all)',
            default: '*',
            required: true,
            validate: fn (string $value) => ($missing = $this->missingEntities($this->split([$value]))) === []
                ? null
                : 'Unknown entity ids: '.implode(', ', $missing),
        )]);
    }

    /**
     * The values of an array option or a multiselect prompt. A "*" selection replaces the others.
     *
     * @param  array<string, string>  $options
     * @return list<string>
     */
    protected function listValue(string $option, string $label, array $options, ?string $default): array
    {
        $values = $this->split($this->option($option));

        if ($values === []) {
            if ($this->input->isInteractive()) {
                $values = array_map('strval', multiselect(label: $label, options: $options, default: $default === null ? [] : [$default], required: true));
            } elseif ($default !== null) {
                $values = [$default];
            }
        }

        return in_array('*', $values, true) ? ['*'] : $values;
    }

    /**
     * The role from the option or an optional prompt.
     */
    protected function roleName(): ?string
    {
        $role = $this->option('role');
        if (! is_string($role) && $this->input->isInteractive()) {
            $role = text(label: 'Attach it to a role? (empty for none)');
        }

        $role = is_string($role) ? trim($role) : '';

        return $role === '' ? null : $role;
    }

    protected function guardName(): string
    {
        $guard = $this->option('guard');
        if (is_string($guard) && trim($guard) !== '') {
            return trim($guard);
        }

        return (string) config('kusikusicms.acl.guard_name', 'web');
    }

    /**
     * The ids that are not "*" and do not exist.
     *
     * @param  list<string>  $ids
     * @return list<string>
     */
    protected function missingEntities(array $ids): array
    {
        $ids = array_values(array_diff($ids, ['*']));
        if ($ids === []) {
            return [];
        }

        $found = Entity::withTrashed()->whereKey($ids)->pluck('id')->map(fn (mixed $id) => (string) $id)->all();

        return array_values(array_diff($ids, $found));
    }

    /**
     * @param  list<EntityAction>|list<EntityScope>  $cases
     * @return array<string, string>
     */
    protected function enumOptions(array $cases): array
    {
        $options = [];
        foreach ($cases as $case) {
            $options[$case->value] = $case->value;
        }

        return $options;
    }

    /**
     * Flatten option values that may hold comma separated lists.
     *
     * @return list<string>
     */
    protected function split(mixed $values): array
    {
        $parts = [];
        foreach ((array) $values as $value) {
            foreach (explode(',', (string) $value) as $part) {
                if (trim($part) !== '') {
                    $parts[] = trim($part);
                }
            }
        }

        return array_values(array_unique($parts));
    }
}
