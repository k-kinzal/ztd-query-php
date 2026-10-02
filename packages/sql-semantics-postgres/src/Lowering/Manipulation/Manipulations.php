<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Manipulation;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Statement\Statement;

/**
 * The entry point of the manipulation family: INSERT, UPDATE, DELETE, MERGE, COPY, prepared statements and cursors.
 *
 * Rule: PG-MANIPULATION-001 (stub — the family implements the bodies; the method
 * signatures are the stable contract and a family may narrow a return type).
 * Scope: see `.agent/plan-pg.md`, family Manipulation. Status: Specified.
 *
 * @visibility SqlSemantics
 */
final class Manipulations
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a statement of the family: `InsertStmt`, `UpdateStmt`, `DeleteStmt`, `MergeStmt`, `CopyStmt`, `PrepareStmt`, `ExecuteStmt`, `DeallocateStmt`, `DeclareCursorStmt`, `FetchStmt` or `ClosePortalStmt`.
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function statement(Node $statement): Statement
    {
        throw ImplementationGap::production($this->lowering->productions->form($statement));
    }

    /**
     * Lowers `PreparableStmt`: the statement a PREPARE, a COPY or a common table expression runs; a data-modifying statement is also a query, whose rows are its RETURNING list.
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function preparable(Node $statement): Statement
    {
        throw ImplementationGap::production($this->lowering->productions->form($statement));
    }
}
