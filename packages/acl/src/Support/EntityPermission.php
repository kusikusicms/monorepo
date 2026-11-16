<?php

namespace KusikusiCMS\Acl\Support;

use KusikusiCMS\Acl\Enums\EntityAction;
use KusikusiCMS\Acl\Enums\EntityScope;
use KusikusiCMS\Acl\Exceptions\InvalidEntityPermissionException;

final readonly class EntityPermission
{
    /** @var list<string> */
    public array $actions;

    /** @var list<string> */
    public array $entityIds;

    /** @var list<string> */
    public array $scopes;

    /**
     * @param  EntityAction|string|array<int, mixed>  $actions
     * @param  string|array<int, mixed>  $entityIds
     * @param  EntityScope|string|array<int, mixed>  $scopes
     */
    public function __construct(
        EntityAction|string|array $actions,
        string|array $entityIds = '*',
        EntityScope|string|array $scopes = '*',
    ) {
        $this->actions = self::normalizeParts($actions);
        $this->entityIds = self::normalizeEntityIds($entityIds);
        $this->scopes = self::normalizeParts($scopes);

        $this->validateParts($this->actions, EntityAction::cases(), 'action');
        $this->validateEntityIds($this->entityIds);
        $this->validateParts($this->scopes, EntityScope::cases(), 'scope');
    }

    /**
     * @param  EntityAction|string|array<int, mixed>  $actions
     * @param  string|array<int, mixed>  $entityIds
     * @param  EntityScope|string|array<int, mixed>  $scopes
     */
    public static function make(EntityAction|string|array $actions, string|array $entityIds = '*', EntityScope|string|array $scopes = '*'): self
    {
        return new self($actions, $entityIds, $scopes);
    }

    /**
     * Parse a permission name using Spatie's omitted-trailing-part wildcard behavior.
     */
    public static function parse(string $name): ?self
    {
        $parts = explode('.', $name);
        $prefix = self::prefix();

        if ($parts[0] !== $prefix) {
            return null;
        }

        if (count($parts) > 4 || in_array('', $parts, true)) {
            throw new InvalidEntityPermissionException("Invalid entity permission name [{$name}].");
        }

        array_shift($parts);
        $parts = array_pad($parts, 3, '*');

        return self::make($parts[0], $parts[1], $parts[2]);
    }

    public function name(): string
    {
        return implode('.', [
            self::prefix(),
            implode(',', $this->actions),
            implode(',', $this->entityIds),
            implode(',', $this->scopes),
        ]);
    }

    public function allows(EntityAction $action): bool
    {
        return in_array('*', $this->actions, true) || in_array($action->value, $this->actions, true);
    }

    /**
     * @param  EntityAction|EntityScope|string|array<int, mixed>  $parts
     * @return list<string>
     */
    private static function normalizeParts(EntityAction|EntityScope|string|array $parts): array
    {
        $values = is_array($parts) ? $parts : [$parts];
        $normalized = [];

        foreach ($values as $value) {
            if ($value instanceof EntityAction || $value instanceof EntityScope) {
                $value = $value->value;
            }

            if (! is_string($value)) {
                throw new InvalidEntityPermissionException('Permission parts must be strings or ACL enums.');
            }

            foreach (explode(',', $value) as $part) {
                $part = trim($part);

                if ($part === '') {
                    throw new InvalidEntityPermissionException('Permission parts cannot be empty.');
                }

                $normalized[] = $part;
            }
        }

        if ($normalized === []) {
            throw new InvalidEntityPermissionException('A permission part cannot be empty.');
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param  string|array<int, mixed>  $entityIds
     * @return list<string>
     */
    private static function normalizeEntityIds(string|array $entityIds): array
    {
        $values = is_array($entityIds) ? $entityIds : [$entityIds];
        $normalized = [];

        foreach ($values as $entityId) {
            if (! is_string($entityId)) {
                throw new InvalidEntityPermissionException('Entity ids must be strings.');
            }

            foreach (explode(',', $entityId) as $part) {
                $part = trim($part);

                if ($part === '') {
                    throw new InvalidEntityPermissionException('Entity ids cannot be empty.');
                }

                $normalized[] = $part;
            }
        }

        if ($normalized === []) {
            throw new InvalidEntityPermissionException('An entity permission must have at least one entity id.');
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param  list<string>  $parts
     * @param  list<EntityAction|EntityScope>  $allowedValues
     */
    private function validateParts(array $parts, array $allowedValues, string $partName): void
    {
        if ($parts === []) {
            throw new InvalidEntityPermissionException("An entity permission must have at least one {$partName}.");
        }

        $allowed = array_map(static fn (EntityAction|EntityScope $value): string => $value->value, $allowedValues);
        $allowed[] = '*';

        foreach ($parts as $part) {
            if ($part === '' || str_contains($part, '.') || str_contains($part, ',')) {
                throw new InvalidEntityPermissionException("Invalid entity permission {$partName} [{$part}].");
            }

            if (! in_array($part, $allowed, true)) {
                throw new InvalidEntityPermissionException("Invalid entity permission {$partName} [{$part}].");
            }
        }
    }

    /**
     * @param  list<string>  $entityIds
     */
    private function validateEntityIds(array $entityIds): void
    {
        if ($entityIds === []) {
            throw new InvalidEntityPermissionException('An entity permission must have at least one entity id.');
        }

        foreach ($entityIds as $entityId) {
            if ($entityId === '' || str_contains($entityId, '.') || str_contains($entityId, ',')) {
                throw new InvalidEntityPermissionException("Invalid entity id [{$entityId}].");
            }
        }
    }

    private static function prefix(): string
    {
        $prefix = config('kusikusicms.acl.prefix', 'entities');

        if (! is_string($prefix) || $prefix === '' || str_contains($prefix, '.') || str_contains($prefix, ',')) {
            throw new InvalidEntityPermissionException('The ACL permission prefix must be a non-empty single part.');
        }

        return $prefix;
    }
}
