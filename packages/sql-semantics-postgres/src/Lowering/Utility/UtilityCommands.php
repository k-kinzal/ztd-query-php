<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Utility;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Statement\Statement;

/**
 * The entry point of the utility family: transactions, configuration, EXPLAIN, maintenance, locking, notification, CALL and DO.
 *
 * Rule: PG-UTILITY-001 (stub — the family implements the bodies; the method
 * signatures are the stable contract and a family may narrow a return type).
 * Scope: see `.agent/plan-pg.md`, family Utility. Status: Specified.
 *
 * @visibility SqlSemantics
 */
final class UtilityCommands
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a statement of the family, such as `TransactionStmt`, `TransactionStmtLegacy`, `VariableSetStmt`, `ExplainStmt` or `VacuumStmt`.
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function statement(Node $statement): Statement
    {
        throw ImplementationGap::production($this->lowering->productions->form($statement));
    }

    /**
     * Lowers `SetResetClause` or `FunctionSetResetClause`: a SET or RESET of a configuration parameter attached to a role, a database or a routine.
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function setReset(Node $clause): Statement
    {
        throw ImplementationGap::production($this->lowering->productions->form($clause));
    }
}
