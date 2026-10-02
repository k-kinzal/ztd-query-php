<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Account;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Statement\Statement;

/**
 * The entry rules of the account family: the methods other families and the statement dispatcher call.
 *
 * Rule: MYSQL-ACCOUNT-ENTRY-001. Scope: users, roles, privileges, passwords and resource groups.
 * The method names, parameters and return types are fixed by the family
 * plan. A method delegates to the rule classes of this family; a method
 * the family has not implemented reports a missing rule.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/account-management-statements.html.
 * Status: Specified.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class AccountRules
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Lowers an account management statement: a node of one of the statement rules this family owns.
     *
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function statement(Node $statement): Statement
    {
        throw ImplementationGap::rule('MySQL account family: statement');
    }

    /**
     * Lowers a production of `create`, `alter` or `drop` that MYSQL-DEFINITION-ROUTES-001 routes to this
     * family.
     *
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function definition(Form $form): Statement
    {
        throw ImplementationGap::rule('MySQL account family: definition');
    }

    /**
     * Lowers RENAME USER: a node of `rename_list`.
     *
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function renameUsers(Node $list): Statement
    {
        throw ImplementationGap::rule('MySQL account family: renameUsers');
    }

    /**
     * Lowers SET PASSWORD: a production of `start_option_value_list` or `option_value_no_option_type`
     * whose first symbol is PASSWORD.
     *
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function setPassword(Form $form): Statement
    {
        throw ImplementationGap::rule('MySQL account family: setPassword');
    }
}
