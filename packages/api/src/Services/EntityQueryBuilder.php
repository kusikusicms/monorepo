<?php

namespace KusikusiCMS\Api\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use KusikusiCMS\Models\Builders\EntityBuilder;
use KusikusiCMS\Models\Entity;

/**
 * Maps the list query parameters of the API to EntityBuilder filters.
 *
 * model, child_of, descendant_of (+depth), ancestor_of, sibling_of, related_by / relating (+kind, tag), live,
 * with_contents (+lang, fields), sort and trashed. Parameters are validated by EntityIndexRequest.
 */
class EntityQueryBuilder
{
    /**
     * Sortable entity columns.
     */
    public const SORTABLE = ['id', 'model', 'view', 'created_at', 'updated_at', 'publish_at', 'unpublish_at'];

    /**
     * Build the query for a list request. $scope applies a tree filter for the given entity id.
     *
     * @param  array{0: string, 1: string}|null  $scope  [filter, entityId], filter is children|descendants|ancestors|siblings
     */
    public function build(Request $request, ?array $scope = null): EntityBuilder
    {
        /** @var EntityBuilder $query */
        $query = Entity::query()->select('entities.*');

        $this->applyTrashed($query, $request);
        $this->applyContents($query, $request);
        $this->applyIncludes($query, $request);

        if ($scope !== null) {
            $this->applyScope($query, $scope[0], $scope[1], $request);
        }

        $this->applyFilters($query, $request);
        $this->applySort($query, $request);

        return EntityQuery::apply($query, $request, 'index');
    }

    /**
     * Load the contents of the requested language when asked to (with_contents, lang or fields).
     */
    public function applyContents(EntityBuilder $query, Request $request): void
    {
        if (! $request->boolean('with_contents') && ! $request->filled('lang') && ! $request->filled('fields')) {
            return;
        }

        $fields = $request->filled('fields') ? $this->list($request->query('fields')) : null;

        $query->withContents(array_filter([
            'lang' => $request->query('lang'),
            'fields' => $fields,
        ], fn ($value) => $value !== null));
    }

    /**
     * Build the query for a single entity (show), with contents, includes and the 'show' modifiers.
     */
    public function single(Request $request, bool $withTrashed = false): EntityBuilder
    {
        /** @var EntityBuilder $query */
        $query = Entity::query()->select('entities.*');

        if ($withTrashed) {
            $query->withTrashed();
        }

        $this->applyContents($query, $request);
        $this->applyIncludes($query, $request);

        return EntityQuery::apply($query, $request, 'show');
    }

    /**
     * include=contents,relations : eager load all contents (contents_by_lang) and the non-ancestor relations.
     */
    public function applyIncludes(EntityBuilder $query, Request $request): void
    {
        $includes = $request->filled('include') ? $this->list($request->query('include')) : [];

        if (in_array('contents', $includes, true)) {
            $query->with('rawContents');
        }

        if (in_array('relations', $includes, true)) {
            $query->with(['relations' => fn ($q) => $q->where('kind', '!=', 'ancestor')]);
        }
    }

    protected function applyTrashed(EntityBuilder $query, Request $request): void
    {
        match ($request->query('trashed')) {
            'with' => $query->withTrashed(),
            'only' => $query->onlyTrashed(),
            default => null,
        };
    }

    /**
     * @param  'children'|'descendants'|'ancestors'|'siblings'|string  $filter
     */
    protected function applyScope(EntityBuilder $query, string $filter, string $entityId, Request $request): void
    {
        match ($filter) {
            'children' => $query->childrenOf($entityId, $request->query('tag')),
            'descendants' => $query->descendantsOf($entityId, $this->depthOptions($request)),
            'ancestors' => $query->ancestorsOf($entityId, ['order' => 'asc']),
            'siblings' => $query->siblingsOf($entityId),
            default => null,
        };
    }

    protected function applyFilters(EntityBuilder $query, Request $request): void
    {
        if ($request->filled('model')) {
            $models = $this->list($request->query('model'));
            $query->ofModel(count($models) === 1 ? $models[0] : $models);
        }

        if ($request->filled('child_of')) {
            $query->childrenOf((string) $request->query('child_of'), $request->query('tag'));
        }

        if ($request->filled('descendant_of')) {
            $query->descendantsOf((string) $request->query('descendant_of'), $this->depthOptions($request));
        }

        if ($request->filled('ancestor_of')) {
            $query->ancestorsOf((string) $request->query('ancestor_of'), ['order' => 'asc']);
        }

        if ($request->filled('sibling_of')) {
            $query->siblingsOf((string) $request->query('sibling_of'));
        }

        foreach (['related_by' => 'relatedBy', 'relating' => 'relating'] as $param => $method) {
            if ($request->filled($param)) {
                $query->{$method}((string) $request->query($param), array_filter([
                    'kind' => $request->filled('kind') ? $this->list($request->query('kind')) : null,
                    'exceptKind' => $request->filled('kind') ? null : ['ancestor'],
                    'tag' => $request->query('tag'),
                ], fn ($value) => $value !== null));
            }
        }

        if ($request->boolean('live')) {
            $query->currentlyLive();
        }
    }

    /**
     * sort=-created_at,content:title : comma separated, "-" for descending, "content:{field}" for a content field.
     */
    protected function applySort(EntityBuilder $query, Request $request): void
    {
        if (! $request->filled('sort')) {
            $query->orderBy('entities.id');

            return;
        }

        foreach ($this->list($request->query('sort')) as $sort) {
            $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
            $column = ltrim($sort, '-');

            if (str_starts_with($column, 'content:')) {
                $query->orderByContent(Str::after($column, 'content:'), $direction, $request->query('lang'));
            } elseif (in_array($column, self::SORTABLE, true)) {
                $query->orderBy('entities.'.$column, $direction);
            }
        }
    }

    /**
     * @return array{maxDepth?: int}
     */
    protected function depthOptions(Request $request): array
    {
        return $request->filled('depth') ? ['maxDepth' => (int) $request->query('depth')] : [];
    }

    /**
     * Split a comma separated value (or accept an array) into a list of non-empty strings.
     *
     * @return array<int, string>
     */
    protected function list(mixed $value): array
    {
        $items = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map(fn ($item) => trim((string) $item), $items), fn ($item) => $item !== ''));
    }
}
