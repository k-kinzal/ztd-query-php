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
 * Rule: PG-MANIPULATION-001. Scope: `PreparableStmt` and the statements of
 * the family, each lowered by the rule of its nonterminal
 * (PG-INSERT-LOWER-001, PG-CHANGE-LOWER-001, PG-MERGE-LOWER-001,
 * PG-COPY-LOWER-001, PG-PREPARED-LOWER-001, PG-CURSOR-LOWER-001).
 * Source: https://www.postgresql.org/docs/17/sql-commands.html. Status: Implemented.
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
     * @throws ImplementationGap When the node is no statement of the family
     */
    public function statement(Node $statement): Statement
    {
        return match ($statement->name) {
            'InsertStmt' => (new InsertRule($this->lowering))->insert($statement),
            'UpdateStmt' => (new ChangeRule($this->lowering))->update($statement),
            'DeleteStmt' => (new ChangeRule($this->lowering))->delete($statement),
            'MergeStmt' => (new MergeRule($this->lowering))->merge($statement),
            'CopyStmt' => (new CopyRule($this->lowering))->copy($statement),
            'PrepareStmt' => (new PreparedRule($this->lowering))->prepare($statement),
            'ExecuteStmt' => (new PreparedRule($this->lowering))->execute($statement),
            'DeallocateStmt' => (new PreparedRule($this->lowering))->deallocate($statement),
            'DeclareCursorStmt' => (new CursorRule($this->lowering))->declare($statement),
            'FetchStmt' => (new CursorRule($this->lowering))->fetch($statement),
            'ClosePortalStmt' => (new CursorRule($this->lowering))->close($statement),
            default => throw ImplementationGap::rule('PG-MANIPULATION-001: statement ' . $statement->name),
        };
    }

    /**
     * Lowers `PreparableStmt`: the statement a PREPARE, a COPY or a common table expression runs; a data-modifying statement is also a query, whose rows are its RETURNING list.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function preparable(Node $statement): Statement
    {
        $form = $this->lowering->productions->form($statement);

        return match ($form->signature) {
            'PreparableStmt: SelectStmt' => $this->lowering->queries->statement($form->node(0)),
            'PreparableStmt: InsertStmt', 'PreparableStmt: UpdateStmt', 'PreparableStmt: DeleteStmt', 'PreparableStmt: MergeStmt' => $this->statement($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }
}
