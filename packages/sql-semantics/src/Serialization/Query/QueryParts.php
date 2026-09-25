<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Query;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\Statements;

/**

 * Writes common query modifiers from their bound operands. @visibility SqlSemantics

 */
final class QueryParts
{
    /**
     * Serializes CTE names, aliases, materialization policies, and their query or write operands.
     */
    public static function with(?\SqlSemantics\Model\Query\WithClause $clause, Dialect $dialect): Tree
    {
        if ($clause === null) {
            return new Tree('with', []);
        }
        $items = [];
        foreach ($clause->definitions as $definition) {
            $labels = array_map(static fn (string $label): Tree => Build::identifier([$label], $dialect), $definition->columns);
            $policy = match ($definition->materialization) {
                \SqlSemantics\Model\Query\Materialization::Default => 'AS', \SqlSemantics\Model\Query\Materialization::Materialized => 'AS MATERIALIZED', \SqlSemantics\Model\Query\Materialization::Inline => 'AS NOT MATERIALIZED',
            };
            $items[] = new Tree('cte', [Build::identifier([$definition->name], $dialect), ...($labels === [] ? [] : [Build::parentheses(Build::separated($labels))]), Build::keyword($policy), Build::parentheses(Statements::write($definition->query)), ...RecursionClauses::write($definition, $dialect)]);
        }
        return new Tree('with', [Build::keyword($clause->recursive ? 'WITH RECURSIVE' : 'WITH'), Build::separated($items)]);
    }

    /**
     * Writes the query's row limit, offset, and ties policy for its dialect.
     */
    public static function pagination(?Expression $limit, ?Expression $offset, bool $withTies, Dialect $dialect): Tree
    {
        if ($withTies) {
            return new Tree('pagination', [...($offset === null ? [] : [Build::keyword('OFFSET'), Expressions::write($offset), Build::keyword('ROWS')]), Build::keyword('FETCH FIRST'), ...($limit === null ? [] : [Expressions::write($limit)]), Build::keyword('ROWS WITH TIES')]);
        }
        $parts = $limit === null ? [] : [Build::keyword('LIMIT'), Expressions::write($limit)];
        if ($offset !== null) {
            if ($limit === null && $dialect !== Dialect::PostgreSql) {
                array_push($parts, Build::keyword('LIMIT'), Build::keyword($dialect === Dialect::Sqlite ? '-1' : '18446744073709551615'));
            }
            array_push($parts, Build::keyword('OFFSET'), Expressions::write($offset));
        }
        return new Tree('pagination', $parts);
    }
}
