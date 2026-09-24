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
     * Writes a query expression; a query followed by a MySQL INTO clause names DUAL before its LIMIT, which MySQL 5.x requires.
     * @throws InvalidStructure
     */
    public static function write(BoundQuery $query, bool $into = false): Tree
    {
        if ($query instanceof BoundSelect) {
            $body = new Tree('select', [Build::keyword('SELECT'), OptimizerHints::write($query->hints), self::quantifier($query->quantifier), ...array_map(static fn (\SqlSemantics\Model\Query\Optimization\SelectOption $option): Tree => Build::keyword($option->value), $query->options), Parts::outputs($query->outputs, $query->origin->dialect), ...self::from($query, $into), Parts::expressions('WHERE', $query->where === null ? [] : [$query->where]), Parts::expressions('GROUP BY', $query->groupBy), Parts::expressions('HAVING', $query->having === null ? [] : [$query->having]), self::windows($query)]);
        } elseif ($query instanceof Statement\ValuesStatement) {
            $body = $query->origin->dialect === \SqlSemantics\Dialect::MySql
                ? new Tree('values', [Build::keyword('VALUES'), Build::separated(array_map(static fn (array $row): Tree => new Tree('row', [Build::keyword('ROW'), Build::parentheses(Build::separated(array_map(Expressions::write(...), $row)))]), $query->rows))])
                : Parts::rows($query->rows);
        } elseif ($query instanceof Statement\CompoundStatement) {
            $body = Build::separated([self::operand($query->left, $query->setOperator), self::operand($query->right)], $query->setOperator->value);
        } elseif ($query instanceof Statement\TableStatement) {
            $body = new Tree('table', [Build::keyword('TABLE'), Relations::write($query->from, $query->origin->dialect)]);
        } else {
            throw new InvalidStructure('A relation description cannot be serialized as a standalone query.');
        }
        return new Tree('query', [QueryParts::with($query->ctes, $query->origin->dialect), $body, Parts::ordering($query->orderBy), QueryParts::pagination($query->limit, $query->offset, $query->withTies, $query->origin->dialect), ...($query instanceof BoundSelect ? [RowLocks::write($query->locks, $query->origin->dialect)] : [])]);
    }

    /**
     * Writes a set operand, parenthesizing one that orders, paginates or locks its rows; a left operand that is itself a set operation of no lower precedence continues the chain without parentheses, which MySQL 5.x requires.
     * @throws InvalidStructure
     */
    public static function operand(BoundQuery $query, ?\SqlSemantics\Model\Query\SetOperator $chain = null): Tree
    {
        if ($query instanceof BoundSelect || $query instanceof Statement\ValuesStatement || $query instanceof Statement\CompoundStatement || $query instanceof Statement\TableStatement) {
            $body = self::write($query);
            $plain = $query->ctes === null && $query->orderBy === [] && $query->limit === null && $query->offset === null && !($query instanceof BoundSelect && $query->locks !== []);
            if ($plain && $query instanceof Statement\CompoundStatement && $chain !== null && (!self::intersection($chain) || self::intersection($query->setOperator))) {
                return $body;
            }
            if ($query instanceof Statement\CompoundStatement || !$plain) {
                return $query->origin->dialect === \SqlSemantics\Dialect::Sqlite ? $body : Build::parentheses($body);
            }
            return $body;
        }
        throw new InvalidStructure('A set operand requires a query expression.');
    }
    /**
     * Writes the FROM clause; a MySQL query without tables that filters or groups, or that limits its rows before an INTO clause, names DUAL, which MySQL 5.x requires before WHERE and before LIMIT ... INTO.
     * @return list<Tree>
     * @throws InvalidStructure
     */
    public static function from(BoundSelect $query, bool $into = false): array
    {
        if ($query->from !== null) {
            return [Build::keyword('FROM'), Relations::write($query->from, $query->origin->dialect)];
        }
        $filtered = $query->where !== null || $query->groupBy !== [] || $query->having !== null || ($into && ($query->limit !== null || $query->offset !== null));
        return $filtered && $query->origin->dialect === \SqlSemantics\Dialect::MySql ? [Build::keyword('FROM DUAL')] : [];
    }

    /**
     * Whether a set operator is INTERSECT, which binds more tightly than UNION and EXCEPT.
     */
    public static function intersection(\SqlSemantics\Model\Query\SetOperator $operator): bool
    {
        return in_array($operator, [\SqlSemantics\Model\Query\SetOperator::Intersect, \SqlSemantics\Model\Query\SetOperator::IntersectAll], true);
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
