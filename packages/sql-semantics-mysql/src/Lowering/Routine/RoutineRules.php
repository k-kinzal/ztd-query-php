<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Statement\Statement;

/**
 * The entry rules of the routine family: the methods other families and the statement dispatcher call.
 *
 * Rule: MYSQL-ROUTINE-ENTRY-001. Scope: stored procedures, functions, triggers, events, loadable functions and
 * compound statements.
 * The method names, parameters and return types are fixed by the family
 * plan. A method delegates to the rule classes of this family; a method
 * the family has not implemented reports a missing rule.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/sql-compound-statements.html.
 * Status: Specified.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class RoutineRules
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a stored program statement: a node of one of the statement rules this family owns.
     *
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function statement(Node $statement): Statement
    {
        throw ImplementationGap::rule('MySQL routine family: statement');
    }

    /**
     * Lowers a production of `alter` or `drop` that MYSQL-DEFINITION-ROUTES-001 routes to this family.
     *
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function definition(Form $form): Statement
    {
        throw ImplementationGap::rule('MySQL routine family: definition');
    }

    /**
     * Lowers CREATE of a stored program: a node of `trigger_tail`, `sp_tail`, `sf_tail`, `event_tail` or
     * `udf_tail`, and the definer.
     *
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function create(Node $tail, ?Account $definer): Statement
    {
        throw ImplementationGap::rule('MySQL routine family: create');
    }
}
