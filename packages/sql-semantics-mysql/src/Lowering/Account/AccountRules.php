<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Account;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Account\Password;
use SqlSemantics\Statement\Statement;

/**
 * The entry rules of the account family: the methods other families and the statement dispatcher call.
 *
 * Rule: MYSQL-ACCOUNT-ENTRY-001. Scope: users, roles, privileges, passwords
 * and resource groups. A statement node is handed to the rule of its
 * nonterminal: GRANT and REVOKE by generation (MYSQL-ACCOUNT-GRANT-001,
 * MYSQL-ACCOUNT-GRANT-LEGACY-001), the user statements
 * (MYSQL-ACCOUNT-USER-001), the role statements (MYSQL-ACCOUNT-ROLE-001) and
 * the resource group statements (MYSQL-ACCOUNT-RESOURCE-GROUP-001); SET
 * PASSWORD comes from the SET rules (MYSQL-ACCOUNT-PASSWORD-001).
 * Terminates: each method delegates once to a rule over a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/account-management-statements.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class AccountRules
{
    /**
     * The 5.6 and 5.7 GRANT and REVOKE productions, which the legacy rule lowers.
     */
    private const LEGACY = ['grant: GRANT clear_privileges grant_command' => true, 'revoke: REVOKE clear_privileges revoke_command' => true];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Lowers an account management statement: a node of one of the statement rules this family owns.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function statement(Node $statement): Statement
    {
        $form = $this->lowering->form($statement);
        if (isset(self::LEGACY[$form->signature])) {
            $legacy = new LegacyGrantRule($this->lowering);

            return $statement->name === 'grant' ? $legacy->grant($form) : $legacy->revoke($form);
        }

        return match ($statement->name) {
            'grant' => (new GrantRule($this->lowering))->grant($form),
            'revoke' => (new GrantRule($this->lowering))->revoke($form),
            'alter_user_stmt' => (new UserStatementRule($this->lowering))->alter($form),
            'drop_user_stmt' => (new UserStatementRule($this->lowering))->drop($form),
            'create_role_stmt', 'drop_role_stmt', 'set_role_stmt' => (new RoleRule($this->lowering))->statement($form),
            'create_resource_group_stmt', 'alter_resource_group_stmt', 'drop_resource_group_stmt', 'set_resource_group_stmt' => (new ResourceGroupRule($this->lowering))->statement($form),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a production of `create`, `alter` or `drop` that MYSQL-DEFINITION-ROUTES-001 routes to this
     * family.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function definition(Form $form): Statement
    {
        return (new UserStatementRule($this->lowering))->definition($form);
    }

    /**
     * Lowers RENAME USER: a node of `rename_list`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function renameUsers(Node $list): Statement
    {
        return (new UserStatementRule($this->lowering))->rename($list);
    }

    /**
     * Lowers SET PASSWORD: a production of `start_option_value_list` or `option_value_no_option_type`
     * whose first symbol is PASSWORD.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function setPassword(Form $form): Statement
    {
        return (new PasswordRule($this->lowering))->statement($form);
    }

    /**
     * Lowers the password operand of a MySQL 5.x SET list item: a node of `text_or_password` (5.6) or `password` (5.7).
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function password(Node $password): Password
    {
        return (new PasswordRule($this->lowering))->password($password);
    }
}
