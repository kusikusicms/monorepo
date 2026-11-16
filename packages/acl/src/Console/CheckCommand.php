<?php

namespace KusikusiCMS\Acl\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use KusikusiCMS\Acl\Enums\EntityAction;
use KusikusiCMS\Acl\Services\PermissionResolver;
use KusikusiCMS\Models\Entity;

class CheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kusikusicms:acl:check
        {action : view, create, update or delete}
        {entity : The entity id (for create, the id of the parent)}
        {--user= : The user id (the guest when omitted)}';

    protected $aliases = ['k:acl:check'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check whether a user or the guest can perform an action on an entity';

    /**
     * Execute the console command. Returns a failure exit code when the action is denied.
     */
    public function handle(PermissionResolver $resolver): int
    {
        $action = EntityAction::tryFrom((string) $this->argument('action'));
        if ($action === null) {
            $this->components->error('The action must be one of: '.implode(', ', array_column(EntityAction::cases(), 'value')).'.');

            return self::FAILURE;
        }

        $entityId = (string) $this->argument('entity');
        $entity = Entity::withTrashed()->find($entityId);
        if (! $entity instanceof Entity) {
            $this->components->error("Entity [{$entityId}] not found (ids are case-sensitive).");

            return self::FAILURE;
        }

        $userId = $this->option('user');
        $user = null;
        if (is_string($userId) && $userId !== '') {
            $user = $this->findUser($userId);
            if ($user === null) {
                $this->components->error("User [{$userId}] not found.");

                return self::FAILURE;
            }
        }

        if (! config('permission.enable_wildcard_permission')) {
            $this->components->warn('Wildcard permissions are disabled (permission.enable_wildcard_permission), entity permissions will not match.');
        }

        $subject = $user === null ? 'The guest' : "User [{$userId}]";
        $target = $action === EntityAction::Create ? "children of [{$entityId}]" : "[{$entityId}]";
        $permission = $action === EntityAction::Create
            ? $resolver->matchingCreatePermission($user, $entity)
            : $resolver->matchingPermission($user, $action, $entity);

        if ($permission === null) {
            $this->components->error("Denied: {$subject} cannot {$action->value} {$target}.");

            return self::FAILURE;
        }

        $this->components->info("Allowed: {$subject} can {$action->value} {$target} (matched {$permission}).");

        return self::SUCCESS;
    }

    protected function findUser(string $id): ?Authenticatable
    {
        $model = config('auth.providers.users.model');
        if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
            return null;
        }

        $user = $model::query()->find($id);

        return $user instanceof Authenticatable ? $user : null;
    }
}
