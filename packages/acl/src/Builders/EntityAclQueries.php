<?php

namespace KusikusiCMS\Acl\Builders;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use KusikusiCMS\Acl\Support\CaseSensitiveWhereIn;
use KusikusiCMS\Acl\Support\PermissionParser;
use KusikusiCMS\Models\Builders\EntityBuilder;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Models\EntityRelation;
use LogicException;

class EntityAclQueries
{
    /**
     * @return Closure(string, ?Authenticatable): EntityBuilder
     */
    public function whereUserCan(): Closure
    {
        return function (string $action = 'view', ?Authenticatable $user = null) {
            // The mixin binds this closure to the builder, so $this is an EntityBuilder at run time.
            /** @var EntityBuilder $this */
            /** @var Model $model */
            $model = $this->getModel(); // @phpstan-ignore varTag.nativeType

            if (! $model instanceof Entity) {
                throw new LogicException('whereUserCan() can only be used on Entity queries.');
            }

            $grants = app(PermissionParser::class)->grants($action, $user ?? auth()->user());
            $entityKey = $model->qualifyColumn($model->getKeyName());
            $parentColumn = $model->qualifyColumn('parent_entity_id');
            $relationTable = (new EntityRelation)->getTable();

            return $this->where(function (Builder $query) use ($grants, $entityKey, $parentColumn, $relationTable): void {
                if ($grants['all']) {
                    $query->whereRaw('1 = 1');

                    return;
                }

                $hasClause = false;
                $addClause = function (Closure $clause) use ($query, &$hasClause): void {
                    if ($hasClause) {
                        $query->orWhere($clause);
                    } else {
                        $query->where($clause);
                        $hasClause = true;
                    }
                };

                if ($grants['self_ids'] !== []) {
                    $addClause(function (Builder $branch) use ($entityKey, $grants): void {
                        CaseSensitiveWhereIn::apply($branch, $entityKey, $grants['self_ids']);
                    });
                }

                if ($grants['children_ids'] !== []) {
                    $addClause(function (Builder $branch) use ($parentColumn, $grants): void {
                        CaseSensitiveWhereIn::apply($branch, $parentColumn, $grants['children_ids']);
                    });
                }

                if ($grants['non_root']) {
                    $addClause(static fn (Builder $branch) => $branch->whereNotNull($parentColumn));
                }

                if ($grants['descendant_ids'] !== []) {
                    $addClause(function (Builder $branch) use ($entityKey, $grants, $relationTable): void {
                        $branch->whereExists(function (QueryBuilder $relations) use ($entityKey, $grants, $relationTable): void {
                            $relations->selectRaw('1')
                                ->from($relationTable.' as acl_entity_relations')
                                ->whereColumn('acl_entity_relations.caller_entity_id', $entityKey)
                                ->where('acl_entity_relations.kind', EntityRelation::RELATION_ANCESTOR);

                            CaseSensitiveWhereIn::apply(
                                $relations,
                                'acl_entity_relations.called_entity_id',
                                $grants['descendant_ids'],
                            );
                        });
                    });
                }

                if (! $hasClause) {
                    $query->whereRaw('0 = 1');
                }
            });
        };
    }
}
