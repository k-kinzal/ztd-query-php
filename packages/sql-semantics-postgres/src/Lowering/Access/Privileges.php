<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Access;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Statement\Statement;

/**
 * The entry point of the access family: roles, privileges, default privileges and owned objects.
 *
 * Rule: PG-ACCESS-001 (stub — the family implements the bodies; the method
 * signatures are the stable contract and a family may narrow a return type).
 * Scope: see `.agent/plan-pg.md`, family Access. Status: Specified.
 *
 * @visibility SqlSemantics
 */
final class Privileges
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a statement of the family, such as `CreateRoleStmt`, `GrantStmt`, `RevokeRoleStmt` or `AlterDefaultPrivilegesStmt`.
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function statement(Node $statement): Statement
    {
        throw ImplementationGap::production($this->lowering->productions->form($statement));
    }
}
