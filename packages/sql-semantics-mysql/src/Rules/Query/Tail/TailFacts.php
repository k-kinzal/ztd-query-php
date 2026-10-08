<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Tail;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\ProgramVariable;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoPosition;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoVariables;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountedList;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountMismatch;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\UndeclaredVariable;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Query;

/**
 * Derives the clauses written after a query: LIMIT, INTO and the locking clauses.
 *
 * Rule: MYSQL-TAIL-FACTS-001. The LIMIT operands and the INTO targets see no
 * column of the query. A LIMIT operand that names a variable of a stored
 * program outside one is reported (ER_SP_UNDECLARED_VAR). The locking
 * clauses follow MYSQL-LOCKED-TABLES-001. An INTO variable list whose length
 * differs from the number of columns of a query whose columns are all known
 * is reported. INTO inside a query block of a query expression raises its
 * deprecation (ER_WARN_DEPRECATED_INNER_INTO): INTO written after the query
 * and before its locking clauses, and INTO in the last query block of a set
 * operation that is written in parentheses, is followed by a clause of the
 * block or is followed by locking clauses of the whole query; INTO at the end
 * of that block, with nothing after it, is the INTO of the whole query.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select-into.html,
 * https://dev.mysql.com/doc/refman/8.4/en/select.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TailFacts
{
    /**
     * Checks the locking clauses, derives the INTO targets and warns about INTO written before the locking clauses.
     */
    public function derive(Derivation $derivation, Environment $outer, QueryFact $fact, Select|QueryStatement $query): void
    {
        (new LockedTables())->check($derivation, $outer, $query);
        $into = $query->into;
        if ($into instanceof IntoVariables) {
            foreach ($into->targets as $target) {
                $derivation->scalar($target, new Environment($derivation->context, $outer));
            }
            if ($fact->shape->complete() && count($fact->shape->slots) !== count($into->targets)) {
                $derivation->report(new CountMismatch(CountedList::IntoVariables, count($fact->shape->slots), count($into->targets)));
            }
        }
        $last = $query instanceof QueryStatement && $query->into === null ? $this->trailing($query->query) : null;
        if ($query->locking !== [] && ($query->intoPosition === IntoPosition::AfterQuery || ($last !== null && $last->into !== null && !$last->clauses()))) {
            Deprecation::raise(Deprecated::IntoInsideQuery, $derivation);
        }
    }

    /**
     * Warns about INTO written inside the last query block of a set operation: inside its parentheses or before a clause of the block.
     */
    public function operand(SetOperation $operation, Derivation $derivation): void
    {
        $block = $operation->right;
        while ($block instanceof ParenthesizedQuery) {
            $block = $block->query;
        }
        if ($block instanceof Select && $block->into !== null && ($block !== $operation->right || $block->clauses())) {
            Deprecation::raise(Deprecated::IntoInsideQuery, $derivation);
        }
    }

    /**
     * Answers the last query block of a set operation written without parentheses, whose INTO, when nothing follows it in the block, the server reads as the INTO of the whole query.
     */
    public function trailing(Query $query): ?Select
    {
        return match (true) {
            $query instanceof ParenthesizedQuery => $this->trailing($query->query),
            $query instanceof QueryExpression && $query->orderBy === [] && $query->limit === null => $this->trailing($query->body),
            $query instanceof SetOperation && $query->right instanceof Select => $query->right,
            default => null,
        };
    }

    /**
     * Derives the operands of a LIMIT clause, which see no column of their query.
     */
    public function limit(?Limit $limit, Derivation $derivation, Environment $outer): void
    {
        if (!$limit instanceof RowLimit) {
            return;
        }
        foreach ([$limit->count, $limit->offset] as $operand) {
            if ($operand === null) {
                continue;
            }
            $derivation->scalar($operand, new Environment($derivation->context, $outer));
            if ($operand instanceof ProgramVariable && !$derivation->inProgram() && Settings::of($derivation->context)->variable($operand->name->value) === null) {
                $derivation->report(new UndeclaredVariable($operand->name));
            }
        }
    }
}
