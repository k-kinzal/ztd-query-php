<?php

declare(strict_types=1);

namespace MySqlMemory\Session\State;

use SqlSemantics\Platform\MySql\Statement as MySql;

/**
 * Maps executable statement models to their SQL command counters.
 *
 * INSERT SELECT and multi-table writes have distinct counters; EXPLAIN counts the command it
 * explains. SHOW COUNT(*) WARNINGS and ERRORS count as SELECT. These distinctions are observable
 * through SHOW SESSION STATUS, independently of an execution's success. All tablespace and
 * logfile-group commands share Com_alter_tablespace. Legacy SET PASSWORD counts as
 * Com_set_option; modern releases expose Com_set_password. Replication aliases use one
 * canonical event name, translated by the status catalog of each release.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/server-status-variables.html.
 *
 * @visibility MySqlMemory
 */
final class StatementKind
{
    /**
     * @var array<class-string<\SqlSemantics\Statement\Node>, string>
     */
    public const KINDS = [
        MySql\Account\AlterRegistration::class => 'Com_alter_user',
        MySql\Account\ResourceGroup\AlterResourceGroup::class => 'Com_alter_resource_group',
        MySql\Account\ResourceGroup\CreateResourceGroup::class => 'Com_create_resource_group',
        MySql\Account\ResourceGroup\DropResourceGroup::class => 'Com_drop_resource_group',
        MySql\Account\ResourceGroup\SetResourceGroup::class => 'Com_set_resource_group',
        MySql\Dml\ImportTable::class => 'Com_import',
        MySql\Dml\Load\LoadTable::class => 'Com_load',
        MySql\EmptyStatement::class => 'Com_empty_query',
        MySql\Replication\Filter\ChangeReplicationFilter::class => 'Com_change_repl_filter',
        MySql\Replication\Group\StartGroupReplication::class => 'Com_group_replication_start',
        MySql\Replication\Group\StopGroupReplication::class => 'Com_group_replication_stop',
        MySql\Replication\Log\BinlogEvent::class => 'Com_binlog',
        MySql\Replication\Log\PurgeLogsBefore::class => 'Com_purge_before_date',
        MySql\Replication\Log\PurgeLogsTo::class => 'Com_purge',
        MySql\Replication\Replica\StartReplica::class => 'Com_replica_start',
        MySql\Replication\Replica\StopReplica::class => 'Com_replica_stop',
        MySql\Replication\Source\ChangeReplicationSource::class => 'Com_change_replication_source',
        MySql\Routine\CreateLoadableFunction::class => 'Com_create_udf',
        MySql\Server\Database\UpgradeDatabaseName::class => 'Com_alter_db_upgrade',
        MySql\Server\Instance\AlterInstance::class => 'Com_alter_instance',
        MySql\Server\Instance\CloneInstance::class => 'Com_clone',
        MySql\Server\Instance\CloneLocal::class => 'Com_clone',
        MySql\Server\Instance\Kill::class => 'Com_kill',
        MySql\Server\Instance\Restart::class => 'Com_restart',
        MySql\Server\Instance\Shutdown::class => 'Com_shutdown',
        MySql\Server\Lock\LockInstance::class => 'Com_lock_instance',
        MySql\Server\Lock\UnlockInstance::class => 'Com_unlock_instance',
        MySql\Server\Plugin\InstallComponent::class => 'Com_install_component',
        MySql\Server\Plugin\InstallPlugin::class => 'Com_install_plugin',
        MySql\Server\Plugin\UninstallComponent::class => 'Com_uninstall_component',
        MySql\Server\Plugin\UninstallPlugin::class => 'Com_uninstall_plugin',
        MySql\Server\Spatial\CreateSpatialReference::class => 'Com_create_spatial_reference_system',
        MySql\Server\Spatial\DropSpatialReference::class => 'Com_drop_spatial_reference_system',
        MySql\Utility\Show\Server\ShowEngineLogs::class => 'Com_show_engine_logs',
        MySql\Utility\Show\ShowParseTree::class => 'Com_show_parse_tree',
        MySql\Server\Storage\AlterLogfileGroup::class => 'Com_alter_tablespace',
        MySql\Server\Storage\AlterTablespace::class => 'Com_alter_tablespace',
        MySql\Server\Storage\AlterTablespaceAccess::class => 'Com_alter_tablespace',
        MySql\Server\Storage\AlterTablespaceDatafile::class => 'Com_alter_tablespace',
        MySql\Server\Storage\AlterUndoTablespace::class => 'Com_alter_tablespace',
        MySql\Server\Storage\CreateLogfileGroup::class => 'Com_alter_tablespace',
        MySql\Server\Storage\CreateTablespace::class => 'Com_alter_tablespace',
        MySql\Server\Storage\CreateUndoTablespace::class => 'Com_alter_tablespace',
        MySql\Server\Storage\DropLogfileGroup::class => 'Com_alter_tablespace',
        MySql\Server\Storage\DropTablespace::class => 'Com_alter_tablespace',
        MySql\Server\Storage\DropUndoTablespace::class => 'Com_alter_tablespace',
        MySql\Server\Storage\RenameTablespace::class => 'Com_alter_tablespace',
        MySql\Dml\Delete::class => 'Com_delete',
        MySql\Dml\MultipleDelete::class => 'Com_delete_multi',
        MySql\Dml\Evaluation::class => 'Com_do',
        MySql\Dml\ProcedureCall::class => 'Com_call_procedure',
        MySql\Dml\Prepared\Prepare::class => 'Com_prepare_sql',
        MySql\Dml\Prepared\Execute::class => 'Com_execute_sql',
        MySql\Dml\Prepared\Deallocate::class => 'Com_dealloc_sql',
        MySql\Dml\Handler\HandlerOpen::class => 'Com_ha_open',
        MySql\Dml\Handler\HandlerClose::class => 'Com_ha_close',
        MySql\Dml\Handler\HandlerScan::class => 'Com_ha_read',
        MySql\Dml\Handler\HandlerIndexRead::class => 'Com_ha_read',
        MySql\Dml\Handler\HandlerIndexSeek::class => 'Com_ha_read',
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
        MySql\Utility\Explain\DescribeTable::class => 'Com_show_fields',
        MySql\Utility\Explain\Help::class => 'Com_help',
        MySql\Utility\Explain\ExplainConnection::class => 'Com_explain_other',
        MySql\Utility\Set\SetVariables::class => 'Com_set_option',
        MySql\Utility\Set\SetTransaction::class => 'Com_set_option',
        MySql\Server\Transaction\Begin::class => 'Com_begin',
        MySql\Server\Transaction\StartTransaction::class => 'Com_begin',
        MySql\Server\Transaction\Commit::class => 'Com_commit',
        MySql\Server\Transaction\Rollback::class => 'Com_rollback',
        MySql\Server\Transaction\Savepoint::class => 'Com_savepoint',
        MySql\Server\Transaction\RollbackToSavepoint::class => 'Com_rollback_to_savepoint',
        MySql\Server\Transaction\ReleaseSavepoint::class => 'Com_release_savepoint',
        MySql\Server\Transaction\Xa\XaStart::class => 'Com_xa_start',
        MySql\Server\Transaction\Xa\XaEnd::class => 'Com_xa_end',
        MySql\Server\Transaction\Xa\XaPrepare::class => 'Com_xa_prepare',
        MySql\Server\Transaction\Xa\XaCommit::class => 'Com_xa_commit',
        MySql\Server\Transaction\Xa\XaRollback::class => 'Com_xa_rollback',
        MySql\Server\Transaction\Xa\XaRecover::class => 'Com_xa_recover',
        MySql\Server\Lock\LockTables::class => 'Com_lock_tables',
        MySql\Server\Lock\UnlockTables::class => 'Com_unlock_tables',
        MySql\Server\Flush\Flush::class => 'Com_flush',
        MySql\Server\Flush\FlushTables::class => 'Com_flush',
        MySql\Server\Maintenance\AnalyzeTable::class => 'Com_analyze',
        MySql\Server\Maintenance\OptimizeTable::class => 'Com_optimize',
        MySql\Server\Maintenance\RepairTable::class => 'Com_repair',
        MySql\Server\Maintenance\CheckTable::class => 'Com_check',
        MySql\Server\Maintenance\ChecksumTable::class => 'Com_checksum',
        MySql\Server\KeyCache\CacheIndex::class => 'Com_assign_to_keycache',
        MySql\Server\KeyCache\LoadIndex::class => 'Com_preload_keys',
        MySql\Server\ForeignServer\CreateServer::class => 'Com_create_server',
        MySql\Server\ForeignServer\AlterServer::class => 'Com_alter_server',
        MySql\Server\ForeignServer\DropServer::class => 'Com_drop_server',
        MySql\Replication\Reset\Reset::class => 'Com_reset',
        MySql\Replication\Reset\ResetPersist::class => 'Com_reset',
        MySql\Routine\CreateProcedure::class => 'Com_create_procedure',
        MySql\Routine\CreateFunction::class => 'Com_create_function',
        MySql\Routine\CreateEvent::class => 'Com_create_event',
        MySql\Routine\AlterEvent::class => 'Com_alter_event',
        MySql\Routine\CreateTrigger::class => 'Com_create_trigger',
        MySql\Routine\Condition\Signal::class => 'Com_signal',
        MySql\Routine\Condition\Resignal::class => 'Com_resignal',
        MySql\Routine\Condition\Diagnostics\GetDiagnostics::class => 'Com_get_diagnostics',
        MySql\View\CreateView::class => 'Com_create_view',
        MySql\View\AlterView::class => 'Com_create_view',
        MySql\View\DropView::class => 'Com_drop_view',
        MySql\Account\CreateUser::class => 'Com_create_user',
        MySql\Account\DropUser::class => 'Com_drop_user',
        MySql\Account\RenameUser::class => 'Com_rename_user',
        MySql\Account\AlterUser::class => 'Com_alter_user',
        MySql\Account\ExpireUserPasswords::class => 'Com_alter_user',
        MySql\Account\CreateRole::class => 'Com_create_role',
        MySql\Account\DropRole::class => 'Com_drop_role',
        MySql\Account\SetDefaultRole::class => 'Com_alter_user_default_role',
        MySql\Account\AlterDefaultRole::class => 'Com_alter_user_default_role',
        MySql\Account\SetRole::class => 'Com_set_role',
        MySql\Account\Privilege\GrantPrivileges::class => 'Com_grant',
        MySql\Account\Privilege\GrantProxy::class => 'Com_grant',
        MySql\Account\Privilege\GrantRoles::class => 'Com_grant_roles',
        MySql\Account\Privilege\RevokePrivileges::class => 'Com_revoke',
        MySql\Account\Privilege\RevokeProxy::class => 'Com_revoke',
        MySql\Account\Privilege\RevokeRoles::class => 'Com_revoke_roles',
        MySql\Account\Privilege\RevokeAll::class => 'Com_revoke_all',
        MySql\Utility\Show\Schema\ShowDatabases::class => 'Com_show_databases',
        MySql\Utility\Show\Schema\ShowTables::class => 'Com_show_tables',
        MySql\Utility\Show\Schema\ShowOpenTables::class => 'Com_show_open_tables',
        MySql\Utility\Show\Schema\ShowTableStatus::class => 'Com_show_table_status',
        MySql\Utility\Show\Schema\ShowColumns::class => 'Com_show_fields',
        MySql\Utility\Show\Schema\ShowKeys::class => 'Com_show_keys',
        MySql\Utility\Show\Schema\ShowCreateTable::class => 'Com_show_create_table',
        MySql\Utility\Show\Schema\ShowCreateView::class => 'Com_show_create_table',
        MySql\Utility\Show\Schema\ShowCreateDatabase::class => 'Com_show_create_db',
        MySql\Utility\Show\Schema\ShowEvents::class => 'Com_show_events',
        MySql\Utility\Show\Schema\ShowTriggers::class => 'Com_show_triggers',
        MySql\Utility\Show\Program\ShowCreateProcedure::class => 'Com_show_create_proc',
        MySql\Utility\Show\Program\ShowCreateFunction::class => 'Com_show_create_func',
        MySql\Utility\Show\Program\ShowCreateTrigger::class => 'Com_show_create_trigger',
        MySql\Utility\Show\Program\ShowCreateEvent::class => 'Com_show_create_event',
        MySql\Utility\Show\Program\ShowProcedureStatus::class => 'Com_show_procedure_status',
        MySql\Utility\Show\Program\ShowFunctionStatus::class => 'Com_show_function_status',
        MySql\Utility\Show\Program\ShowProcedureCode::class => 'Com_show_procedure_code',
        MySql\Utility\Show\Program\ShowFunctionCode::class => 'Com_show_function_code',
        MySql\Utility\Show\Replication\ShowBinaryLogs::class => 'Com_show_binlogs',
        MySql\Utility\Show\Replication\ShowBinaryLogStatus::class => 'Com_show_binary_log_status',
        MySql\Utility\Show\Replication\ShowBinlogEvents::class => 'Com_show_binlog_events',
        MySql\Utility\Show\Replication\ShowRelaylogEvents::class => 'Com_show_relaylog_events',
        MySql\Utility\Show\Replication\ShowReplicas::class => 'Com_show_replicas',
        MySql\Utility\Show\Replication\ShowReplicaStatus::class => 'Com_show_replica_status',
        MySql\Utility\Show\Server\ShowStatus::class => 'Com_show_status',
        MySql\Utility\Show\Server\ShowVariables::class => 'Com_show_variables',
        MySql\Utility\Show\Server\ShowCharacterSet::class => 'Com_show_charsets',
        MySql\Utility\Show\Server\ShowCollation::class => 'Com_show_collations',
        MySql\Utility\Show\Server\ShowPlugins::class => 'Com_show_plugins',
        MySql\Utility\Show\Server\ShowEngineCatalog::class => 'Com_show_storage_engines',
        MySql\Utility\Show\Server\ShowEngineMutex::class => 'Com_show_engine_mutex',
        MySql\Utility\Show\Server\ShowEngineStatus::class => 'Com_show_engine_status',
        MySql\Utility\Show\Server\ShowGrants::class => 'Com_show_grants',
        MySql\Utility\Show\Server\ShowCreateUser::class => 'Com_show_create_user',
        MySql\Utility\Show\Server\ShowPrivileges::class => 'Com_show_privileges',
        MySql\Utility\Show\Server\ShowProcesslist::class => 'Com_show_processlist',
        MySql\Utility\Show\Session\ShowProfile::class => 'Com_show_profile',
        MySql\Utility\Show\Session\ShowProfiles::class => 'Com_show_profiles',
        MySql\Utility\Show\Session\ShowWarnings::class => 'Com_show_warnings',
        MySql\Utility\Show\Session\ShowErrors::class => 'Com_show_errors',
        MySql\Utility\Show\Session\ShowWarningCount::class => 'Com_select',
        MySql\Utility\Show\Session\ShowErrorCount::class => 'Com_select',
    ];

    /**
     * Answers the counter of a supported statement kind.
     */
    public static function of(\SqlSemantics\Statement\Node $statement): ?string
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
        if ($statement instanceof MySql\Routine\DropProgram) {
            return 'Com_drop_' . strtolower($statement->kind->value);
        }
        if ($statement instanceof MySql\Routine\AlterRoutine) {
            return 'Com_alter_' . strtolower($statement->kind->value);
        }
        if ($statement instanceof MySql\Account\SetPassword) {
            return in_array($statement->profile()->grammar, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true) ? 'Com_set_option' : 'Com_set_password';
        }
        if ($statement instanceof MySql\QueryRoot) {
            return 'Com_select';
        }

        return self::KINDS[$statement::class] ?? null;
    }
}
