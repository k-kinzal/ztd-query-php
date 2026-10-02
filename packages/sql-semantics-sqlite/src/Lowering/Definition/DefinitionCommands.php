<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Definition;

use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Statement\Statement;

/**
 * Lowers the commands that define, change or administer the database.
 *
 * Rule: SQLITE-DEFINITION-COMMANDS-001. Scope: every `cmd` production that is
 * not a query command: table, view, index and virtual table definitions,
 * ALTER and DROP, transactions, ATTACH and DETACH, VACUUM, PRAGMA, REINDEX and
 * ANALYZE. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class DefinitionCommands
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a command of this family.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function command(Form $form): Statement
    {
        return match ($form->signature) {
            'cmd: create_table create_table_args' => $this->lowering->tables->create($form->node(0), $form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }
}
