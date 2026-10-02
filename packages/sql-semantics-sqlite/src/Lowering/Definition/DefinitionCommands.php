<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Definition;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Statement\Inspection\Explain;
use SqlSemantics\Platform\Sqlite\Statement\Inspection\ExplainMode;
use SqlSemantics\Statement\Statement;

/**
 * Lowers the commands that define, change or administer the database.
 *
 * Rule: SQLITE-DEFINITION-COMMANDS-001. Scope: every `cmd` production that is
 * not a query command: table, view, index and virtual table definitions,
 * ALTER and DROP, transactions, ATTACH and DETACH, VACUUM, PRAGMA, REINDEX and
 * ANALYZE; and the `explain` prefix. Each family rule answers null for a
 * command of another family; a command no family claims is an implementation
 * gap. Terminates: a fixed number of family rules is asked once each.
 * Source: https://sqlite.org/lang.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class DefinitionCommands
{
    private readonly TransactionRule $transactions;

    private readonly MaintenanceRule $maintenance;

    private readonly ConnectionRule $connections;

    private readonly CreateTableRule $tables;

    private readonly SchemaRule $schema;

    private readonly VirtualTableRule $virtualTables;

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
        $this->transactions = new TransactionRule($lowering);
        $this->maintenance = new MaintenanceRule($lowering);
        $this->connections = new ConnectionRule($lowering);
        $this->tables = new CreateTableRule($lowering);
        $this->schema = new SchemaRule($lowering);
        $this->virtualTables = new VirtualTableRule($lowering);
    }

    /**
     * Lowers a command of this family.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function command(Form $form): Statement
    {
        return $this->transactions->command($form)
            ?? $this->maintenance->command($form)
            ?? $this->connections->command($form)
            ?? $this->tables->command($form)
            ?? $this->schema->command($form)
            ?? $this->virtualTables->command($form)
            ?? throw ImplementationGap::production($form);
    }

    /**
     * Wraps a command in the inspection request written before it.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function explained(Node $explain, Statement $command): Statement
    {
        $form = $this->lowering->productions->form($explain);

        return match ($form->signature) {
            'explain: EXPLAIN' => new Explain(ExplainMode::Program, $command),
            'explain: EXPLAIN QUERY PLAN' => new Explain(ExplainMode::QueryPlan, $command),
            default => throw ImplementationGap::production($form),
        };
    }
}
