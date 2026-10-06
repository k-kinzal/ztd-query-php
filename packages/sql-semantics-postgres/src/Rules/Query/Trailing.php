<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Query;

use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\SelectOptions;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Limit\CommaLimit;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Limit\LimitCount;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Limit\Offset;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Limit\RowLimit;
use SqlSemantics\Platform\PostgreSql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Join;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\JoinOn;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\RelationList;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;

/**
 * Finds the expression a query is written to end with, so that a statement can tell whether a token it writes after the query would continue that expression.
 *
 * Rule: PG-QUERY-TRAILING-001. A query ends with an expression when its last
 * written clause does: the last unaliased select-list item, a FROM item that
 * ends with a JOIN ... ON condition, WHERE, the last GROUP BY expression,
 * HAVING, the last ORDER BY expression without a direction, NULLS or USING,
 * or a LIMIT or OFFSET count. A set operation ends where its right operand
 * does, a query with a WITH clause or trailing clauses where its body or
 * those clauses do. VALUES, TABLE, a parenthesized query, a WINDOW clause,
 * a locking clause, FETCH, an alias and INTO end with a closing token or a
 * name. CREATE VIEW ... WITH CHECK OPTION and CREATE TABLE AS / CREATE
 * MATERIALIZED VIEW ... WITH [NO] DATA write WITH after the query, which an
 * IS JSON test at the end would take (Precedence::takesUniqueness).
 * Terminates: each step descends into a part of a finite tree.
 * Source: https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/gram.y
 * (simple_select, select_limit, json_key_uniqueness_constraint_opt). Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Trailing
{
    /**
     * Answers the expression a query ends with, or null when it ends with a closing token or a name.
     */
    public function query(Query $query): ?Scalar
    {
        if ($query instanceof QueryExpression) {
            return $query->options !== null ? $this->options($query->options) : $this->query($query->body);
        }
        if ($query instanceof SetOperation) {
            return $this->query($query->right);
        }

        return $query instanceof Select ? $this->select($query) : null;
    }

    /**
     * Tells whether a WITH written after a query would continue an IS JSON test the query ends with.
     */
    public function takesWith(Query $query): bool
    {
        $last = $this->query($query);

        return $last !== null && (new Precedence())->takesUniqueness($last);
    }

    /**
     * Answers the expression a SELECT ends with, or null.
     */
    public function select(Select $select): ?Scalar
    {
        if ($select->options !== null) {
            return $this->options($select->options);
        }
        if ($select->windows !== []) {
            return null;
        }
        $group = $select->groupBy === [] ? null : $select->groupBy[count($select->groupBy) - 1];
        $target = $select->targets === [] ? null : $select->targets[count($select->targets) - 1];

        return match (true) {
            $select->having !== null => $select->having,
            $group !== null => $group instanceof Scalar ? $group : null,
            $select->where !== null => $select->where,
            $select->from !== null => $this->relation($select->from),
            $select->into !== null => null,
            default => $target instanceof ExpressionTarget && $target->alias === null ? $target->expression : null,
        };
    }

    /**
     * Answers the expression the ORDER BY, limit and locking clauses end with, or null.
     */
    public function options(SelectOptions $options): ?Scalar
    {
        if (($options->locking !== [] || $options->readOnly) && !$options->lockingFirst) {
            return null;
        }
        if ($options->limit !== null) {
            return $this->limit($options->limit);
        }
        $last = $options->order[count($options->order) - 1];

        return $last->direction === null && $last->nulls === null && $last->using === null ? $last->expression : null;
    }

    /**
     * Answers the count a limit ends with, or null for LIMIT ALL and FETCH.
     */
    public function limit(RowLimit $limit): ?Scalar
    {
        $last = $limit->offsetFirst ? ($limit->count ?? $limit->offset) : ($limit->offset ?? $limit->count);

        return match (true) {
            $last instanceof Offset => $last->start,
            $last instanceof CommaLimit => $last->offset,
            $last instanceof LimitCount => $last->count,
            default => null,
        };
    }

    /**
     * Answers the JOIN ... ON condition a FROM item ends with, or null.
     */
    public function relation(Relation $relation): ?Scalar
    {
        if ($relation instanceof RelationList) {
            return $this->relation($relation->items[count($relation->items) - 1]);
        }
        if (!$relation instanceof Join) {
            return null;
        }

        return match (true) {
            $relation->condition instanceof JoinOn => $relation->condition->condition,
            $relation->condition === null => $this->relation($relation->right),
            default => null,
        };
    }
}
