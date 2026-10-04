<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Access;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Statement\Statement;

/**
 * The entry point of the access family: roles, privileges, role membership, default privileges and owned objects.
 *
 * Rule: PG-ACCESS-001. Scope: the statement nonterminals of the family
 * (`CreateRoleStmt`, `CreateUserStmt`, `CreateGroupStmt`, `AlterRoleStmt`,
 * `AlterRoleSetStmt`, `AlterGroupStmt`, `DropRoleStmt`, `GrantStmt`,
 * `RevokeStmt`, `GrantRoleStmt`, `RevokeRoleStmt`,
 * `AlterDefaultPrivilegesStmt`, `DropOwnedStmt`, `ReassignOwnedStmt`), each
 * handed to the rule of its group. Termination: each statement is lowered
 * by one rule; lists are flattened iteratively.
 * Source: https://www.postgresql.org/docs/17/sql-commands.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Privileges
{
    /**
     * The rule group of each statement nonterminal.
     */
    private const GROUPS = [
        'CreateRoleStmt' => 'role', 'CreateUserStmt' => 'role', 'CreateGroupStmt' => 'role', 'AlterRoleStmt' => 'role',
        'AlterRoleSetStmt' => 'role', 'AlterGroupStmt' => 'role', 'DropRoleStmt' => 'role',
        'GrantStmt' => 'grant', 'RevokeStmt' => 'grant',
        'GrantRoleStmt' => 'membership', 'RevokeRoleStmt' => 'membership',
        'AlterDefaultPrivilegesStmt' => 'defaults', 'DropOwnedStmt' => 'defaults', 'ReassignOwnedStmt' => 'defaults',
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a statement of the family, such as `CreateRoleStmt`, `GrantStmt`, `RevokeRoleStmt` or `AlterDefaultPrivilegesStmt`.
     *
     * @throws ImplementationGap When the nonterminal is not a statement of the family
     */
    public function statement(Node $statement): Statement
    {
        $group = self::GROUPS[$statement->name] ?? throw ImplementationGap::production($this->lowering->productions->form($statement));

        return match ($group) {
            'role' => (new RoleRule($this->lowering))->statement($statement),
            'grant' => (new GrantRule($this->lowering))->statement($statement),
            'membership' => (new MembershipRule($this->lowering))->statement($statement),
            'defaults' => (new DefaultPrivilegeRule($this->lowering))->statement($statement),
        };
    }
}
