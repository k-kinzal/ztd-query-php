<?php

declare(strict_types=1);

namespace MySqlMemory\Session\State;

use SqlSemantics\Platform\MySql\Statement as MySql;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Statement;

/**
 * Maps executable statement models to their SQL command counters.
 *
 * INSERT SELECT and multi-table writes have distinct counters; EXPLAIN counts the command it
 * explains. SHOW COUNT(*) WARNINGS and ERRORS count as SELECT. These distinctions are observable
 * through SHOW SESSION STATUS, independently of an execution's success.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/server-status-variables.html.
 *
 * @visibility MySqlMemory
 */
final class StatementKind
{
    /**
     * @var array<class-string<Statement>, string>
     */
    public const KINDS = [
        MySql\Dml\Delete::class => 'Com_delete',
        MySql\Dml\MultipleDelete::class => 'Com_delete_multi',
        MySql\Dml\Evaluation::class => 'Com_do',
        MySql\Dml\ProcedureCall::class => 'Com_call_procedure',
        MySql\Dml\Prepared\Prepare::class => 'Com_prepare_sql',
        MySql\Dml\Prepared\Execute::class => 'Com_execute_sql',
        MySql\Dml\Prepared\Deallocate::class => 'Com_dealloc_sql',
        MySql\Table\CreateTable::class => 'Com_create_table',
        MySql\Table\CreateTableLike::class => 'Com_create_table',
        MySql\Table\CreateIndex::class => 'Com_create_index',
        MySql\Alter\AlterTable::class => 'Com_alter_table',
        MySql\Alter\DropTable::class => 'Com_drop_table',
        MySql\Alter\DropIndex::class => 'Com_drop_index',
        MySql\Alter\RenameTable::class => 'Com_rename_table',
        MySql\Alter\TruncateTable::class => 'Com_truncate',
        MySql\Server\Database\CreateDatabase::class => 'Com_create_db',
        MySql\Server\Database\AlterDatabase::class => 'Com_alter_db',
        MySql\Server\Database\DropDatabase::class => 'Com_drop_db',
        MySql\Utility\Explain\UseDatabase::class => 'Com_change_db',
        MySql\Utility\Set\SetVariables::class => 'Com_set_option',
        MySql\Server\Transaction\Begin::class => 'Com_begin',
        MySql\Server\Transaction\StartTransaction::class => 'Com_begin',
        MySql\Server\Transaction\Commit::class => 'Com_commit',
        MySql\Server\Transaction\Rollback::class => 'Com_rollback',
        MySql\Server\Flush\Flush::class => 'Com_flush',
        MySql\Server\Flush\FlushTables::class => 'Com_flush',
        MySql\Server\Maintenance\AnalyzeTable::class => 'Com_analyze',
        MySql\Server\Maintenance\OptimizeTable::class => 'Com_optimize',
        MySql\Server\Maintenance\RepairTable::class => 'Com_repair',
        MySql\Server\Maintenance\CheckTable::class => 'Com_check',
        MySql\Server\Maintenance\ChecksumTable::class => 'Com_checksum',
        MySql\Routine\CreateProcedure::class => 'Com_create_procedure',
        MySql\Routine\CreateFunction::class => 'Com_create_function',
        MySql\Routine\CreateEvent::class => 'Com_create_event',
        MySql\Routine\AlterEvent::class => 'Com_alter_event',
        MySql\Routine\CreateTrigger::class => 'Com_create_trigger',
        MySql\View\CreateView::class => 'Com_create_view',
        MySql\View\AlterView::class => 'Com_create_view',
        MySql\View\DropView::class => 'Com_drop_view',
        MySql\Utility\Show\Server\ShowStatus::class => 'Com_show_status',
        MySql\Utility\Show\Server\ShowVariables::class => 'Com_show_variables',
        MySql\Utility\Show\Session\ShowWarnings::class => 'Com_show_warnings',
        MySql\Utility\Show\Session\ShowErrors::class => 'Com_show_errors',
        MySql\Utility\Show\Session\ShowWarningCount::class => 'Com_select',
        MySql\Utility\Show\Session\ShowErrorCount::class => 'Com_select',
    ];

    /**
     * Answers the counter of a supported statement kind.
     */
    public static function of(Statement $statement): ?string
    {
        if ($statement instanceof MySql\Dml\Insert\InsertRows || $statement instanceof MySql\Dml\Insert\InsertSet || $statement instanceof MySql\Dml\Insert\InsertQuery) {
            return ($statement->into->replace ? 'Com_replace' : 'Com_insert') . ($statement instanceof MySql\Dml\Insert\InsertQuery ? '_select' : '');
        }
        if ($statement instanceof MySql\Dml\Update) {
            return count($statement->tables) !== 1 || !$statement->tables[0] instanceof MySql\Relation\TableReference ? 'Com_update_multi' : 'Com_update';
        }
        if ($statement instanceof MySql\Utility\Explain\Explain) {
            return self::of($statement->statement);
        }
        if ($statement instanceof Query) {
            return 'Com_select';
        }

        return self::KINDS[$statement::class] ?? null;
    }
}
