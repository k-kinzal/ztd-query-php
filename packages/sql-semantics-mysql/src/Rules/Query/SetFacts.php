<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Query\Tail\TailFacts;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion;
use SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Query;

/**
 * Derives the facts of a set operation.
 *
 * Rule: MYSQL-SET-FACTS-001. Both operands are derived in the enclosing
 * environment, each its own query block; the output columns combine them by
 * MYSQL-RESULT-SLOTS-001. In the query of a recursive common table
 * expression (MYSQL-WITH-001) a right operand that refers to the table sees
 * it with the columns of the left operand, all nullable, and those columns
 * are the output, whose types the recursive part does not change. The
 * operands of a leading union (MYSQL-LEADING-UNION-001) are derived the
 * same way and combined for the operation that continues it; the form
 * needs MySQL 5.6 or 5.7, and the own LIMIT of its last SELECT needs 5.6.
 * INTO in the last operand follows MYSQL-TAIL-FACTS-001.
 * Terminates: both operands are strict parts. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/set-operations.html,
 * https://dev.mysql.com/doc/refman/8.4/en/with.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SetFacts
{
    /**
     * Derives both operands and answers the output.
     */
    public function derive(SetOperation $operation, Derivation $derivation, Environment $outer): QueryFact
    {
        $fact = $this->operands($operation->left, $operation->operator, $operation->right, $derivation, $outer);
        (new TailFacts())->operand($operation, $derivation);

        return $fact;
    }

    /**
     * Derives the two operands of a set operator and answers the output.
     */
    public function operands(Query|LeadingUnion $leftQuery, SetOperator $operator, Query $rightQuery, Derivation $derivation, Environment $outer): QueryFact
    {
        $left = $leftQuery instanceof LeadingUnion ? $this->leading($leftQuery, $derivation, $outer) : $derivation->query($leftQuery, $outer);
        $tables = new CommonTables();
        $pending = $tables->pending($outer, $rightQuery, $derivation);
        $right = $derivation->query($rightQuery, $pending === null ? $outer : $tables->anchored($outer, $pending, $left, $derivation));
        $fact = new QueryFact((new ResultSlots())->combine($left, $right, $operator, $derivation, [$this->combined($leftQuery), $this->combined($rightQuery)]), $derivation->context->columnNames);

        return $pending === null ? $fact : $tables->nullable($left, $derivation);
    }

    /**
     * Tells whether an operand is itself a set operation, also in parentheses or with its own ORDER BY or LIMIT.
     */
    public function combined(Query|LeadingUnion $operand): bool
    {
        while ($operand instanceof ParenthesizedQuery || $operand instanceof QueryExpression) {
            $operand = $operand instanceof ParenthesizedQuery ? $operand->query : $operand->body;
        }

        return $operand instanceof SetOperation || $operand instanceof OrderedSetOperation || $operand instanceof LeadingUnion;
    }

    /**
     * Derives the operands of a leading union and answers the rows they combine to.
     *
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the release does not read the form
     */
    public function leading(LeadingUnion $leading, Derivation $derivation, Environment $outer): QueryFact
    {
        $release = $derivation->context->profile->grammar;
        Check::input(in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true), 'A SELECT that keeps its own clauses before a later UNION needs MySQL 5.6 or 5.7.');
        Check::input($leading->right->limit === null || $release === GrammarRelease::MySql5651, 'A SELECT that keeps its own LIMIT before a later UNION needs MySQL 5.6.');

        return $this->operands($leading->left, SetOperator::Union, $leading->right, $derivation, $outer);
    }
}
