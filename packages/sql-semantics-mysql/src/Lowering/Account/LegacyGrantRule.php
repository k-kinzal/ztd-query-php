<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Account;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantProxy;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeAll;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokePrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeProxy;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Statement\Statement;

/**
 * Lowers GRANT and REVOKE of MySQL 5.6 and 5.7.
 *
 * Rule: MYSQL-ACCOUNT-GRANT-LEGACY-001. Scope: grant, revoke (5.x
 * productions), grant_command, revoke_command. The statements are the same
 * structures as the 8.0 ones; GRANT also carries the account clauses of 5.x
 * (IDENTIFIED per account, REQUIRE, resource limits in WITH), and 5.6 REVOKE
 * reads its accounts with the GRANT account list. The marker
 * clear_privileges holds no operand. Constructs: GrantPrivileges,
 * GrantProxy, RevokePrivileges, RevokeAll, RevokeProxy. Terminates: every
 * child is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/5.7/en/grant.html,
 * https://dev.mysql.com/doc/refman/5.7/en/revoke.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class LegacyGrantRule
{
    /**
     * The object kinds of the grant_command and revoke_command productions that name one.
     */
    private const KINDS = [
        'grant_command: grant_privileges ON opt_table grant_ident TO_SYM grant_list require_clause grant_options' => ObjectKind::Table,
        'grant_command: grant_privileges ON FUNCTION_SYM grant_ident TO_SYM grant_list require_clause grant_options' => ObjectKind::Function,
        'grant_command: grant_privileges ON PROCEDURE_SYM grant_ident TO_SYM grant_list require_clause grant_options' => ObjectKind::Procedure,
        'revoke_command: grant_privileges ON opt_table grant_ident FROM grant_list' => ObjectKind::Table,
        'revoke_command: grant_privileges ON FUNCTION_SYM grant_ident FROM grant_list' => ObjectKind::Function,
        'revoke_command: grant_privileges ON PROCEDURE_SYM grant_ident FROM grant_list' => ObjectKind::Procedure,
        'revoke_command: grant_privileges ON opt_table grant_ident FROM user_list' => ObjectKind::Table,
        'revoke_command: grant_privileges ON FUNCTION_SYM grant_ident FROM user_list' => ObjectKind::Function,
        'revoke_command: grant_privileges ON PROCEDURE_SYM grant_ident FROM user_list' => ObjectKind::Procedure,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `grant: GRANT clear_privileges grant_command`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function grant(Form $form): Statement
    {
        if ($form->signature !== 'grant: GRANT clear_privileges grant_command') {
            throw ImplementationGap::production($form);
        }
        $this->lowering->options->skip($form->node(1));
        $command = $this->lowering->form($form->node(2));
        $lists = new UserListRule($this->lowering, new IdentificationRule($this->lowering));
        $clauses = new ClauseRule($this->lowering);
        if ($command->signature === 'grant_command: PROXY_SYM ON user TO_SYM grant_list opt_grant_option') {
            return new GrantProxy($this->lowering->users->account($command->node(2)), $lists->granted($command->node(4)), $clauses->grantOption($command->node(5)));
        }
        $kind = self::KINDS[$command->signature] ?? throw ImplementationGap::production($command);
        $this->table($command);
        $privileges = new PrivilegeRule($this->lowering);

        return new GrantPrivileges(
            $privileges->privileges($command->node(0)),
            $kind,
            $privileges->level($command->node(3)),
            $lists->granted($command->node(5)),
            $clauses->tls($command->node(6)),
            $clauses->grantOptions($command->node(7)),
        );
    }

    /**
     * Lowers `revoke: REVOKE clear_privileges revoke_command`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function revoke(Form $form): Statement
    {
        if ($form->signature !== 'revoke: REVOKE clear_privileges revoke_command') {
            throw ImplementationGap::production($form);
        }
        $this->lowering->options->skip($form->node(1));
        $command = $this->lowering->form($form->node(2));
        $privileges = new PrivilegeRule($this->lowering);
        if ($command->signature === 'revoke_command: ALL opt_privileges , GRANT OPTION FROM grant_list' || $command->signature === 'revoke_command: ALL opt_privileges , GRANT OPTION FROM user_list') {
            $privileges->words($command->node(1));

            return new RevokeAll(false, $this->users($command->node(6)));
        }
        if ($command->signature === 'revoke_command: PROXY_SYM ON user FROM grant_list' || $command->signature === 'revoke_command: PROXY_SYM ON user FROM user_list') {
            return new RevokeProxy(false, $this->lowering->users->account($command->node(2)), $this->users($command->node(4)));
        }
        $kind = self::KINDS[$command->signature] ?? throw ImplementationGap::production($command);
        $this->table($command);

        return new RevokePrivileges(false, $privileges->privileges($command->node(0)), $kind, $privileges->level($command->node(3)), $this->users($command->node(5)));
    }

    /**
     * Confirms the optional TABLE word of a command that names no routine kind.
     */
    public function table(Form $command): void
    {
        $kind = $command->node->children[2] ?? null;
        if ($kind instanceof Node) {
            $this->lowering->options->present($kind);
        }
    }

    /**
     * Lowers the accounts of a 5.x REVOKE: a grant_list (5.6) or a user_list (5.7).
     *
     * @return non-empty-list<UserSpecification>
     * @throws ImplementationGap When a production has no rule
     */
    public function users(Node $list): array
    {
        if ($list->name === 'grant_list') {
            return (new UserListRule($this->lowering, new IdentificationRule($this->lowering)))->granted($list);
        }

        return (new GrantRule($this->lowering))->specifications($list);
    }
}
