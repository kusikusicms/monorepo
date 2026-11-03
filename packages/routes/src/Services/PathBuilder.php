<?php

namespace KusikusiCMS\Routes\Services;

use InvalidArgumentException;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Models\EntityContent;

/**
 * Builds entity paths from the slugs of the entity and its ancestors in one language.
 *
 * It walks parent_entity_id directly instead of the ancestor relations, because those are
 * refreshed by the EntityObserver after the EntitySaved event listeners run.
 */
class PathBuilder
{
    /**
     * The configured root entity id (only its tree gets routes), or null when every entity is routable.
     */
    public function rootEntityId(): ?string
    {
        $domains = (array) config('kusikusicms.routes.domains', []);
        $site = reset($domains);
        $root = is_array($site) ? ($site['root_entity_id'] ?? null) : null;

        return $root === null || $root === '' ? null : (string) $root;
    }

    /**
     * The EntityContent field holding the slug.
     */
    public function slugField(): string
    {
        return (string) config('kusikusicms.routes.slug_field', 'slug');
    }

    /**
     * The chain from the entity up to the top of its tree (inclusive): the configured root entity,
     * or an entity without parent when no root is configured.
     * Returns null when the entity is outside the configured root's tree (or the chain has a cycle).
     *
     * The given instance is used as is, so an unsaved parent_entity_id change is taken into account.
     *
     * @return list<Entity>|null
     */
    public function ancestry(Entity $entity): ?array
    {
        $root = $this->rootEntityId();
        $chain = [$entity];
        $visited = [(string) $entity->id => true];
        $current = $entity;

        while ($current->id !== $root && $current->parent_entity_id !== null) {
            $parentId = (string) $current->parent_entity_id;
            if (isset($visited[$parentId])) {
                return null;
            }
            $parent = Entity::withTrashed()->find($parentId);
            if (! $parent instanceof Entity) {
                break;
            }
            $visited[$parentId] = true;
            $chain[] = $parent;
            $current = $parent;
        }

        if ($root !== null && $current->id !== $root) {
            return null;
        }

        return $chain;
    }

    /**
     * Whether the entity belongs to the configured root's tree.
     */
    public function inScope(Entity $entity): bool
    {
        return $this->ancestry($entity) !== null;
    }

    /**
     * Build the path of an entity in a language.
     *
     * - The top entity contributes its slug, or nothing when the slug is empty or missing ("/").
     * - Every other entity in the chain needs a non-empty slug, otherwise no path can be built.
     * - The entity itself needs a slug content (or an override), otherwise no path is built.
     *
     * @param  string|null  $slugOverride  The slug to use for the entity instead of the stored one
     *
     * @throws InvalidArgumentException When a slug contains "/"
     */
    public function build(Entity $entity, string $lang, ?string $slugOverride = null): ?string
    {
        $chain = $this->ancestry($entity);
        if ($chain === null) {
            return null;
        }

        $slugs = $this->slugsFor(array_map(fn (Entity $node) => (string) $node->id, $chain), $lang);
        if ($slugOverride !== null) {
            $slugs[(string) $entity->id] = $slugOverride;
        }
        if (! array_key_exists((string) $entity->id, $slugs)) {
            return null;
        }

        $path = null;
        foreach (array_reverse($chain) as $index => $node) {
            $slug = $this->sanitize($slugs[(string) $node->id] ?? null);
            if ($index === 0) {
                $path = $slug === null || $slug === '' ? '/' : '/'.$slug;

                continue;
            }
            $path = $this->join($path, $slug);
            if ($path === null) {
                return null;
            }
        }

        return $path;
    }

    /**
     * Append a slug to a parent path. Returns null when the parent has no path or the slug is empty.
     */
    public function join(?string $parentPath, ?string $slug): ?string
    {
        if ($parentPath === null || $slug === null || $slug === '') {
            return null;
        }

        return rtrim($parentPath, '/').'/'.$slug;
    }

    /**
     * Trim whitespace and slashes from a slug.
     *
     * @throws InvalidArgumentException When the slug contains "/" after trimming
     */
    public function sanitize(?string $slug): ?string
    {
        if ($slug === null) {
            return null;
        }
        $slug = trim($slug, " \t\n\r\0\x0B/");
        if (str_contains($slug, '/')) {
            throw new InvalidArgumentException("The slug [{$slug}] cannot contain \"/\".");
        }

        return $slug;
    }

    /**
     * The stored slugs of some entities in a language, keyed by entity id.
     *
     * @param  list<string>  $entityIds
     * @return array<string, string|null>
     */
    public function slugsFor(array $entityIds, string $lang): array
    {
        if ($entityIds === []) {
            return [];
        }

        $slugs = [];
        EntityContent::query()
            ->whereIn('entity_id', $entityIds)
            ->where('lang', $lang)
            ->where('field', $this->slugField())
            ->get(['entity_id', 'text'])
            ->each(function (EntityContent $content) use (&$slugs) {
                $slugs[(string) $content->entity_id] = $content->text;
            });

        return $slugs;
    }
}
