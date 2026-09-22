<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Query;

use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Parts;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\Scalar\Windows;

/**

 * Writes query stages in their SQL evaluation structure. @visibility SqlSemantics

 */
final class Queries
{
    /**
     * @throws InvalidStructure
     */
    public static function write(BoundQuery $query): Tree
    {
        if ($query instanceof BoundSelect) {
            $body = new Tree('select', [Build::keyword('SELECT'), OptimizerHints::write($query->hints), self::quantifier($query->quantifier), Parts::outputs($query->outputs, $query->origin->dialect), ...($query->from === null ? [] : [Build::keyword('FROM'), Relations::write($query->from, $query->origin->dialect)]), Parts::expressions('WHERE', $query->where === null ? [] : [$query->where]), Parts::expressions('GROUP BY', $query->groupBy), Parts::expressions('HAVING', $query->having === null ? [] : [$query->having]), self::windows($query)]);
        } elseif ($query instanceof Statement\ValuesStatement) {
            $body = $query->origin->dialect === \SqlSemantics\Dialect::MySql
                ? new Tree('values', [Build::keyword('VALUES'), Build::separated(array_map(static fn (array $row): Tree => new Tree('row', [Build::keyword('ROW'), Build::parentheses(Build::separated(array_map(Expressions::write(...), $row)))]), $query->rows))])
                : Parts::rows($query->rows);
        } elseif ($query instanceof Statement\CompoundStatement) {
            $body = Build::separated([self::operand($query->left), self::operand($query->right)], $query->setOperator->value);
        } elseif ($query instanceof Statement\TableStatement) {
            $body = new Tree('table', [Build::keyword('TABLE'), Relations::write($query->from, $query->origin->dialect)]);
        } else {
            throw new InvalidStructure('A relation description cannot be serialized as a standalone query.');
        }
        return new Tree('query', [QueryParts::with($query->ctes, $query->origin->dialect), $body, Parts::ordering($query->orderBy), QueryParts::pagination($query->limit, $query->offset, $query->withTies, $query->origin->dialect), ...($query instanceof BoundSelect ? [RowLocks::write($query->locks, $query->origin->dialect)] : [])]);
    }

    /**
     * @throws InvalidStructure
     */
    public static function operand(BoundQuery $query): Tree
    {
        if ($query instanceof BoundSelect || $query instanceof Statement\ValuesStatement || $query instanceof Statement\CompoundStatement || $query instanceof Statement\TableStatement) {
            $body = self::write($query);
            if ($query instanceof Statement\CompoundStatement || $query->ctes !== null || $query->orderBy !== [] || $query->limit !== null || $query->offset !== null) {
                return $query->origin->dialect === \SqlSemantics\Dialect::Sqlite ? $body : Build::parentheses($body);
            }
            return $body;
        }
        throw new InvalidStructure('A set operand requires a query expression.');
    }
    /**
     * @throws InvalidStructure
     */
    public static function quantifier(\SqlSemantics\Model\Query\Quantifier $quantifier): Tree
    {
        return match (true) {
            $quantifier instanceof \SqlSemantics\Model\Query\AllRows => new Tree('all', []),
            $quantifier instanceof \SqlSemantics\Model\Query\DistinctRows => Build::keyword('DISTINCT'),
            $quantifier instanceof \SqlSemantics\Model\Query\DistinctOn => new Tree('distinct-on', [Build::keyword('DISTINCT ON'), Build::parentheses(Build::separated(array_map(Expressions::write(...), $quantifier->keys)))]),
            default => throw new InvalidStructure('Unclassified duplicate elimination operation.'),
        };
    }

    /**
     * Serializes the named window definitions owned by this SELECT scope.
     */
    public static function windows(BoundSelect $query): Tree
    {
        $definitions = array_map(static fn (\SqlSemantics\Model\Window\Definition $definition): Tree => new Tree('window-definition', [Build::identifier([$definition->name], $query->origin->dialect), Build::keyword('AS'), Windows::write($definition->specification, $query->origin->dialect)]), $query->windows);
        return new Tree('windows', $definitions === [] ? [] : [Build::keyword('WINDOW'), Build::separated($definitions)]);
    }
}
