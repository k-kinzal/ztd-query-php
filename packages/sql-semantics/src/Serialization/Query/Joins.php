<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Query;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Join;
use SqlSemantics\Model\JoinKind;
use SqlSemantics\Model\Relation\Joining\CrossJoin;
use SqlSemantics\Model\Relation\Joining\NaturalJoin;
use SqlSemantics\Model\Relation\Joining\OnJoin;
use SqlSemantics\Model\Relation\Joining\SharedColumn;
use SqlSemantics\Model\Relation\Joining\UnconditionalOuterJoin;
use SqlSemantics\Model\Relation\Joining\UsingJoin;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Serialization\Expressions;

/**
 * Serializes each join's matching operation and preserves merged-column semantics.
 *
 * @visibility SqlSemantics
 */
final class Joins
{
    /**
     * Writes a join; an inner NATURAL join is written without INNER, which MySQL 5.x rejects after NATURAL.
     * @throws InvalidStructure
     */
    public static function write(Join $join, Dialect $dialect): Tree
    {
        $kind = match ($join->kind) {
            JoinKind::Inner => $join instanceof NaturalJoin ? 'JOIN' : 'INNER JOIN',
            JoinKind::Cross => 'CROSS JOIN',
            JoinKind::Left => 'LEFT JOIN',
            JoinKind::Right => 'RIGHT JOIN',
            JoinKind::Full => 'FULL JOIN',
        };
        $suffix = match (true) {
            $join instanceof OnJoin => [Build::keyword('ON'), Expressions::write($join->condition)],
            $join instanceof UsingJoin => [Build::keyword('USING'), Build::parentheses(Build::separated(array_map(static fn (SharedColumn $column): Tree => Build::identifier([$column->name], $dialect), $join->columns)))],
            $join instanceof NaturalJoin, $join instanceof CrossJoin, $join instanceof UnconditionalOuterJoin => [],
            default => throw new InvalidStructure('Every join requires a classified matching operation.'),
        };
        $right = Relations::write($join->right, $dialect);
        $left = Relations::write($join->left, $dialect);
        return new Tree('join', [$join->left instanceof Join ? Build::parentheses($left) : $left, Build::keyword(($join instanceof NaturalJoin ? 'NATURAL ' : '') . $kind), $join->right instanceof Join ? Build::parentheses($right) : $right, ...$suffix]);
    }
}
