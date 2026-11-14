<?php

namespace KusikusiCMS\Acl\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Adds a `whereIn` that compares entity ids case-sensitively, like Spatie's wildcard matcher.
 * SQLite and PostgreSQL compare strings case-sensitively already; MySQL needs BINARY.
 */
class CaseSensitiveWhereIn
{
    /**
     * @param  Builder<Model>|QueryBuilder  $query
     * @param  list<string>  $values
     */
    public static function apply(Builder|QueryBuilder $query, string $column, array $values): void
    {
        $base = $query instanceof Builder ? $query->getQuery() : $query;
        $connection = $base->getConnection();
        $driver = $connection instanceof Connection ? $connection->getDriverName() : null;

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $placeholders = implode(', ', array_fill(0, count($values), '?'));
            $query->whereRaw('BINARY '.$base->getGrammar()->wrap($column).' IN ('.$placeholders.')', $values);

            return;
        }

        $query->whereIn($column, $values);
    }
}
