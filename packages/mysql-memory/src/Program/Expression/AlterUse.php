<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Expression;

use MySqlMemory\Command\Definition\AlterTableCommand;
use MySqlMemory\Command\Definition\TableChange;
use MySqlMemory\Command\Definition\TableLayout;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredAlterTable;

/**
 * Opens a retained ALTER target and applies its ordered declaration changes.
 * The local layout remains unpublished when the generation condition is reached.
 *
 * @visibility MySqlMemory
 */
final class AlterUse
{
    /**
     * Resolves the target database without opening the table during preparation.
     */
    public function header(DeferredAlterTable $definition, Session $session, ?string $database = null): string
    {
        (new \MySqlMemory\Command\Definition\Expression\GenerationExecution())->variables($definition, $session);
        $database = $definition->table->schema->value ?? $database ?? $session->variables->database;
        if ($database === '') {
            throw QueryError::NoDatabase->error();
        }
        return $database;
    }

    /**
     * Preserves table and column errors before the retained generation condition.
     *
     * @throws SqlError When target lookup or a declaration is refused
     */
    public function raise(DeferredAlterTable $definition, Session $session, bool $invocation = true, ?string $database = null): \MySqlMemory\Result\Reply
    {
        if ($invocation) {
            \MySqlMemory\Session\State\StatementCounters::query($session);
            \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_alter_table');
            $session->diagnostics->clear();
        }
        $database = $this->header($definition, $session, $database);
        $session->transaction->commit();
        $name = $definition->table->name->value;
        $table = (new AlterTableCommand())->table($session, $database, $name);
        $layout = TableLayout::of($table->definition);
        $change = new TableChange($layout, $name, $database, $session->settings()->release(), (string) $session->variables->read('collation_database'));
        $change->apply($definition->commands);
        $rewrite = new \MySqlMemory\Command\Definition\Expression\GenerationRewrite();
        $columns = array_map($rewrite->column(...), array_column($layout->columns, 0));
        if ((new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\LegacySubqueries())->columns($columns)) {
            throw (new \MySqlMemory\Command\Definition\Expression\GenerationCondition())->columns($columns, $database, $name, true);
        }
        $statement = new \SqlSemantics\Platform\MySql\Statement\Alter\AlterTable($definition->table, $rewrite->commands($definition), $definition->ignore);
        $execution = new \MySqlMemory\Command\Definition\Expression\GenerationExecution();
        $reply = $execution->run($statement, $session, $database);
        $execution->display($table, $definition, $session);
        return $reply;
    }
}
