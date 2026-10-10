<?php

declare(strict_types=1);

namespace MySqlMemory\Command;

use MySqlMemory\Command\Write\ChangeCommand;
use MySqlMemory\Command\Write\InsertCommand;
use MySqlMemory\Command\Write\MultipleChangeCommand;
use MySqlMemory\Error\Family\StatementError;
use ReflectionClass;
use SqlSemantics\Platform\MySql\Statement as MySql;
use SqlSemantics\Platform\MySql\Statement\Dml\Delete;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertSet;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Statement;

/**
 * Chooses the command that executes a resolved statement.
 *
 * A statement the emulator does not execute is refused with ER_NOT_SUPPORTED_YET.
 *
 * @visibility MySqlMemory
 */
final class Dispatcher
{
    /**
     * The command of each statement kind whose command takes nothing from the statement.
     *
     * Every statement class is final, so the class of a statement finds its command.
     *
     * @var array<class-string<Statement>, class-string<Command>>
     */
    public const COMMANDS = [
        MySql\Server\Instance\Kill::class => Admin\KillCommand::class,
        MySql\Server\Instance\CloneLocal::class => Admin\InstanceCommand::class,
        MySql\Server\Instance\Shutdown::class => Admin\InstanceCommand::class,
        MySql\Server\Instance\Restart::class => Admin\InstanceCommand::class,
        MySql\Server\Instance\CloneInstance::class => Admin\InstanceCommand::class,
        MySql\Dml\MultipleDelete::class => MultipleChangeCommand::class,
        MySql\Table\CreateTable::class => Definition\CreateTableCommand::class,
        MySql\Alter\DropTable::class => Definition\DropTableCommand::class,
        MySql\Alter\TruncateTable::class => Definition\DropTableCommand::class,
        MySql\Alter\AlterTable::class => Definition\AlterTableCommand::class,
        MySql\Table\CreateIndex::class => Definition\AlterTableCommand::class,
        MySql\Alter\DropIndex::class => Definition\AlterTableCommand::class,
        MySql\Alter\RenameTable::class => Definition\RenameTableCommand::class,
        MySql\Table\CreateTableLike::class => Definition\CreateTableLikeCommand::class,
        MySql\Server\Database\AlterDatabase::class => Definition\AlterDatabaseCommand::class,
        MySql\Dml\Load\LoadTable::class => Access\LoadDataCommand::class,
        MySql\Dml\ImportTable::class => Access\ImportTableCommand::class,
        MySql\Server\Lock\LockTables::class => Access\LockTablesCommand::class,
        MySql\Server\Lock\UnlockTables::class => Access\LockTablesCommand::class,
        MySql\Dml\Handler\HandlerOpen::class => Access\HandlerCommand::class,
        MySql\Dml\Handler\HandlerClose::class => Access\HandlerCommand::class,
        MySql\Dml\Handler\HandlerScan::class => Access\HandlerCommand::class,
        MySql\Dml\Handler\HandlerIndexRead::class => Access\HandlerCommand::class,
        MySql\Dml\Handler\HandlerIndexSeek::class => Access\HandlerCommand::class,
        MySql\Server\Database\CreateDatabase::class => DatabaseCommand::class,
        MySql\Server\Database\DropDatabase::class => DatabaseCommand::class,
        MySql\Utility\Explain\UseDatabase::class => DatabaseCommand::class,
        MySql\Utility\Set\SetVariables::class => SetCommand::class,
        MySql\Server\Transaction\Begin::class => TransactionCommand::class,
        MySql\Server\Transaction\StartTransaction::class => TransactionCommand::class,
        MySql\Server\Transaction\Commit::class => TransactionCommand::class,
        MySql\Server\Transaction\Rollback::class => TransactionCommand::class,
        MySql\Utility\Show\Session\ShowWarnings::class => WarningsCommand::class,
        MySql\Utility\Show\Session\ShowErrors::class => WarningsCommand::class,
        MySql\Utility\Show\Session\ShowWarningCount::class => WarningsCommand::class,
        MySql\Utility\Show\Session\ShowErrorCount::class => WarningsCommand::class,
        MySql\Dml\Evaluation::class => DoCommand::class,
        MySql\Utility\Show\Schema\ShowTables::class => ShowTablesCommand::class,
        MySql\Utility\Show\Schema\ShowDatabases::class => Show\ShowDatabasesCommand::class,
        MySql\Routine\Condition\Signal::class => Condition\SignalCommand::class,
        MySql\Routine\Condition\Diagnostics\GetDiagnostics::class => Condition\DiagnosticsCommand::class,
        MySql\Dml\Prepared\Prepare::class => Prepared\PreparedCommand::class,
        MySql\Dml\Prepared\Execute::class => Prepared\PreparedCommand::class,
        MySql\Dml\Prepared\Deallocate::class => Prepared\PreparedCommand::class,
        MySql\Utility\Show\Program\ShowProcedureCode::class => Program\ProgramCodeCommand::class,
        MySql\Utility\Show\Program\ShowFunctionCode::class => Program\ProgramCodeCommand::class,
        MySql\Routine\CreateProcedure::class => Program\RoutineCommand::class,
        MySql\Routine\CreateFunction::class => Program\RoutineCommand::class,
        MySql\Routine\AlterRoutine::class => Program\RoutineCommand::class,
        MySql\Routine\CreateTrigger::class => Program\TriggerCommand::class,
        MySql\Routine\CreateEvent::class => Program\EventCommand::class,
        MySql\Routine\AlterEvent::class => Program\EventCommand::class,
        MySql\Routine\DropProgram::class => Program\DropProgramCommand::class,
        MySql\Dml\ProcedureCall::class => Program\CallCommand::class,
        MySql\Utility\Show\Program\ShowCreateProcedure::class => Program\ShowCreateProgramCommand::class,
        MySql\Utility\Show\Program\ShowCreateFunction::class => Program\ShowCreateProgramCommand::class,
        MySql\Utility\Show\Program\ShowCreateTrigger::class => Program\ShowCreateProgramCommand::class,
        MySql\Utility\Show\Program\ShowCreateEvent::class => Program\ShowCreateProgramCommand::class,
        MySql\Utility\Show\Program\ShowProcedureStatus::class => Program\ShowRoutinesCommand::class,
        MySql\Utility\Show\Program\ShowFunctionStatus::class => Program\ShowRoutinesCommand::class,
        MySql\Utility\Show\Schema\ShowTriggers::class => Program\ShowTriggersCommand::class,
        MySql\Utility\Show\Schema\ShowEvents::class => Program\ShowEventsCommand::class,
        MySql\View\CreateView::class => View\ViewCommand::class,
        MySql\View\AlterView::class => View\ViewCommand::class,
        MySql\View\DropView::class => View\DropViewCommand::class,
        MySql\Utility\Show\Schema\ShowCreateView::class => View\ShowCreateViewCommand::class,
        MySql\Utility\Show\Schema\ShowColumns::class => Show\ShowColumnsCommand::class,
        MySql\Utility\Explain\DescribeTable::class => Show\ShowColumnsCommand::class,
        MySql\Utility\Show\Schema\ShowKeys::class => Show\ShowKeysCommand::class,
        MySql\Utility\Show\Schema\ShowTableStatus::class => Show\ShowTableStatusCommand::class,
        MySql\Utility\Show\Schema\ShowOpenTables::class => Show\ShowOpenTablesCommand::class,
        MySql\Utility\Show\Server\ShowVariables::class => Show\Server\ShowVariablesCommand::class,
        MySql\Utility\Show\Server\ShowStatus::class => Show\Server\ShowVariablesCommand::class,
        MySql\Utility\Show\Server\ShowCollation::class => Show\Server\ShowCollationCommand::class,
        MySql\Utility\Show\Server\ShowCharacterSet::class => Show\Server\ShowCollationCommand::class,
        MySql\Utility\Show\Server\ShowEngineCatalog::class => Show\Server\ShowEnginesCommand::class,
        MySql\Utility\Show\Server\ShowEngineLogs::class => Show\Server\ShowEnginesCommand::class,
        MySql\Utility\Show\Server\ShowEngineMutex::class => Show\Server\ShowEnginesCommand::class,
        MySql\Utility\Show\Server\ShowEngineStatus::class => Show\Server\ShowEnginesCommand::class,
        MySql\Utility\Show\Server\ShowPlugins::class => Show\Server\ShowPluginsCommand::class,
        MySql\Utility\Show\Server\ShowPrivileges::class => Show\Server\ShowPrivilegesCommand::class,
        MySql\Utility\Show\Server\ShowProcesslist::class => Show\Server\ShowProcesslistCommand::class,
        MySql\Utility\Show\Session\ShowProfiles::class => Show\Server\ShowProfilesCommand::class,
        MySql\Utility\Show\Session\ShowProfile::class => Show\Server\ShowProfilesCommand::class,
        MySql\Utility\Explain\Help::class => Show\Server\HelpCommand::class,
        MySql\Utility\Explain\Explain::class => Explain\ExplainCommand::class,
        MySql\Utility\Explain\ExplainConnection::class => Explain\ExplainCommand::class,
        MySql\Utility\Show\Schema\ShowCreateTable::class => Show\ShowCreateTableCommand::class,
        MySql\Utility\Show\Schema\ShowCreateDatabase::class => Show\ShowCreateDatabaseCommand::class,
        MySql\Server\Maintenance\CheckTable::class => Maintenance\AdministrationCommand::class,
        MySql\Server\Maintenance\OptimizeTable::class => Maintenance\AdministrationCommand::class,
        MySql\Server\Maintenance\RepairTable::class => Maintenance\AdministrationCommand::class,
        MySql\Server\Maintenance\AnalyzeTable::class => Maintenance\AdministrationCommand::class,
        MySql\Server\KeyCache\CacheIndex::class => Maintenance\AdministrationCommand::class,
        MySql\Server\KeyCache\LoadIndex::class => Maintenance\AdministrationCommand::class,
        MySql\Server\Maintenance\ChecksumTable::class => Maintenance\ChecksumCommand::class,
        MySql\Account\CreateUser::class => Account\CreateUserCommand::class,
        MySql\Account\CreateRole::class => Account\CreateUserCommand::class,
        MySql\Account\DropUser::class => Account\DropUserCommand::class,
        MySql\Account\DropRole::class => Account\DropUserCommand::class,
        MySql\Account\AlterUser::class => Account\AlterUserCommand::class,
        MySql\Account\ExpireUserPasswords::class => Account\AlterUserCommand::class,
        MySql\Account\RenameUser::class => Account\RenameUserCommand::class,
        MySql\Account\SetPassword::class => Account\SetPasswordCommand::class,
        MySql\Account\Privilege\GrantPrivileges::class => Account\GrantCommand::class,
        MySql\Account\Privilege\GrantRoles::class => Account\GrantCommand::class,
        MySql\Account\Privilege\GrantProxy::class => Account\GrantCommand::class,
        MySql\Account\Privilege\RevokePrivileges::class => Account\RevokeCommand::class,
        MySql\Account\Privilege\RevokeRoles::class => Account\RevokeCommand::class,
        MySql\Account\Privilege\RevokeProxy::class => Account\RevokeCommand::class,
        MySql\Account\Privilege\RevokeAll::class => Account\RevokeCommand::class,
        MySql\Account\SetRole::class => Account\RoleCommand::class,
        MySql\Account\SetDefaultRole::class => Account\RoleCommand::class,
        MySql\Account\AlterDefaultRole::class => Account\RoleCommand::class,
        MySql\Utility\Show\Server\ShowGrants::class => Account\ShowGrantsCommand::class,
        MySql\Utility\Show\Server\ShowCreateUser::class => Account\ShowCreateUserCommand::class,
        MySql\Utility\Set\SetTransaction::class => Transaction\SetTransactionCommand::class,
        MySql\Server\Transaction\Savepoint::class => Transaction\SavepointCommand::class,
        MySql\Server\Transaction\RollbackToSavepoint::class => Transaction\SavepointCommand::class,
        MySql\Server\Transaction\ReleaseSavepoint::class => Transaction\SavepointCommand::class,
        MySql\Server\Transaction\Xa\XaStart::class => Transaction\XaCommand::class,
        MySql\Server\Transaction\Xa\XaEnd::class => Transaction\XaCommand::class,
        MySql\Server\Transaction\Xa\XaPrepare::class => Transaction\XaCommand::class,
        MySql\Server\Transaction\Xa\XaCommit::class => Transaction\XaCommand::class,
        MySql\Server\Transaction\Xa\XaRollback::class => Transaction\XaCommand::class,
        MySql\Server\Transaction\Xa\XaRecover::class => Transaction\XaCommand::class,
        MySql\Account\ResourceGroup\CreateResourceGroup::class => Admin\ResourceGroupCommand::class,
        MySql\Account\ResourceGroup\AlterResourceGroup::class => Admin\ResourceGroupCommand::class,
        MySql\Account\ResourceGroup\DropResourceGroup::class => Admin\ResourceGroupCommand::class,
        MySql\Account\ResourceGroup\SetResourceGroup::class => Admin\ResourceGroupCommand::class,
        MySql\Server\ForeignServer\CreateServer::class => Admin\ForeignServerCommand::class,
        MySql\Server\ForeignServer\AlterServer::class => Admin\ForeignServerCommand::class,
        MySql\Server\ForeignServer\DropServer::class => Admin\ForeignServerCommand::class,
        MySql\Routine\CreateLoadableFunction::class => Admin\PluginCommand::class,
        MySql\Server\Plugin\InstallPlugin::class => Admin\PluginCommand::class,
        MySql\Server\Plugin\UninstallPlugin::class => Admin\PluginCommand::class,
        MySql\Server\Plugin\InstallComponent::class => Admin\PluginCommand::class,
        MySql\Server\Plugin\UninstallComponent::class => Admin\PluginCommand::class,
        MySql\Server\Storage\CreateTablespace::class => Admin\TablespaceCommand::class,
        MySql\Server\Storage\CreateUndoTablespace::class => Admin\TablespaceCommand::class,
        MySql\Server\Storage\AlterTablespace::class => Admin\TablespaceCommand::class,
        MySql\Server\Storage\AlterTablespaceAccess::class => Admin\TablespaceCommand::class,
        MySql\Server\Storage\AlterTablespaceDatafile::class => Admin\TablespaceCommand::class,
        MySql\Server\Storage\AlterUndoTablespace::class => Admin\TablespaceCommand::class,
        MySql\Server\Storage\RenameTablespace::class => Admin\TablespaceCommand::class,
        MySql\Server\Storage\DropTablespace::class => Admin\TablespaceCommand::class,
        MySql\Server\Storage\DropUndoTablespace::class => Admin\TablespaceCommand::class,
        MySql\Server\Storage\CreateLogfileGroup::class => Admin\LogfileGroupCommand::class,
        MySql\Server\Storage\AlterLogfileGroup::class => Admin\LogfileGroupCommand::class,
        MySql\Server\Storage\DropLogfileGroup::class => Admin\LogfileGroupCommand::class,
        MySql\Server\Spatial\CreateSpatialReference::class => Admin\SpatialReferenceCommand::class,
        MySql\Server\Spatial\DropSpatialReference::class => Admin\SpatialReferenceCommand::class,
        MySql\Server\Instance\AlterInstance::class => Admin\InstanceCommand::class,
        MySql\Server\Lock\LockInstance::class => Admin\InstanceCommand::class,
        MySql\Server\Lock\UnlockInstance::class => Admin\InstanceCommand::class,
        MySql\Server\Flush\Flush::class => Admin\FlushCommand::class,
        MySql\Server\Flush\FlushTables::class => Admin\FlushCommand::class,
        MySql\Account\AlterRegistration::class => Admin\RegistrationCommand::class,
        MySql\Replication\Reset\ResetPersist::class => Admin\PersistCommand::class,
        MySql\Replication\Replica\StartReplica::class => Replication\ReplicaCommand::class,
        MySql\Replication\Replica\StopReplica::class => Replication\ReplicaCommand::class,
        MySql\Replication\Source\ChangeReplicationSource::class => Replication\ReplicaCommand::class,
        MySql\Replication\Filter\ChangeReplicationFilter::class => Replication\ReplicaCommand::class,
        MySql\Replication\Reset\Reset::class => Replication\ReplicaCommand::class,
        MySql\Replication\Group\StartGroupReplication::class => Replication\ReplicaCommand::class,
        MySql\Replication\Group\StopGroupReplication::class => Replication\ReplicaCommand::class,
        MySql\Replication\Log\PurgeLogsTo::class => Replication\BinaryLogCommand::class,
        MySql\Replication\Log\PurgeLogsBefore::class => Replication\BinaryLogCommand::class,
        MySql\Replication\Log\BinlogEvent::class => Replication\BinaryLogCommand::class,
        MySql\Utility\Show\Replication\ShowReplicaStatus::class => Replication\ReplicationShowCommand::class,
        MySql\Utility\Show\Replication\ShowReplicas::class => Replication\ReplicationShowCommand::class,
        MySql\Utility\Show\Replication\ShowBinaryLogs::class => Replication\ReplicationShowCommand::class,
        MySql\Utility\Show\Replication\ShowBinaryLogStatus::class => Replication\ReplicationShowCommand::class,
        MySql\Utility\Show\Replication\ShowBinlogEvents::class => Replication\ReplicationShowCommand::class,
        MySql\Utility\Show\Replication\ShowRelaylogEvents::class => Replication\ReplicationShowCommand::class,
    ];

    /**
     * Answers the command of a statement.
     *
     * Queries, INSERT, UPDATE, DELETE and RESIGNAL choose their command from the statement;
     * every other kind takes its command from COMMANDS.
     *
     * @throws \MySqlMemory\Error\SqlError When no command executes the statement
     */
    public function command(Statement $statement): Command
    {
        $command = self::COMMANDS[$statement::class] ?? null;

        return match (true) {
            $statement instanceof Query => new QueryCommand(),
            $statement instanceof InsertRows, $statement instanceof InsertSet, $statement instanceof InsertQuery => new View\ViewWriteCommand(new InsertCommand()),
            $statement instanceof Update && MultipleChangeCommand::joined($statement) => new MultipleChangeCommand(),
            $statement instanceof Update, $statement instanceof Delete => new View\ViewWriteCommand(new ChangeCommand()),
            $statement instanceof Resignal => Condition\SignalCommand::resignal($statement),
            self::handles($statement) => match (true) {
                $statement instanceof MySql\Routine\CreateTrigger => new Program\TriggerCommand(false),
                $statement instanceof MySql\Routine\CreateEvent => new Program\EventCommand(false),
                default => new Program\RoutineCommand(false),
            },
            $command !== null => new $command(),
            default => throw StatementError::NotSupportedYet->error((new ReflectionClass($statement))->getShortName()),
        };
    }

    /**
     * Tells whether a statement creates a stored program whose body declares a handler, which the server creates leaving the diagnostics area as it was.
     */
    public static function handles(Statement $statement): bool
    {
        return self::program($statement) && (new \MySqlMemory\Evaluation\Compile\Walker())->find($statement, MySql\Routine\Program\HandlerDeclaration::class) !== [];
    }

    /**
     * Tells whether a statement creates a stored program: a procedure, a function, a trigger or an event.
     */
    public static function program(\SqlSemantics\Statement\Node $statement): bool
    {
        return $statement instanceof MySql\Routine\CreateProcedure || $statement instanceof MySql\Routine\CreateFunction || $statement instanceof MySql\Routine\CreateTrigger || $statement instanceof MySql\Routine\CreateEvent;
    }
}
