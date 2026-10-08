<?php

declare(strict_types=1);

namespace MySqlMemory\Command;

use MySqlMemory\Command\Access\HandlerCommand;
use MySqlMemory\Command\Access\ImportTableCommand;
use MySqlMemory\Command\Access\LoadDataCommand;
use MySqlMemory\Command\Access\LockTablesCommand;
use MySqlMemory\Command\Definition\AlterDatabaseCommand;
use MySqlMemory\Command\Definition\AlterTableCommand;
use MySqlMemory\Command\Definition\CreateTableCommand;
use MySqlMemory\Command\Definition\CreateTableLikeCommand;
use MySqlMemory\Command\Definition\DropTableCommand;
use MySqlMemory\Command\Definition\RenameTableCommand;
use MySqlMemory\Command\Explain\ExplainCommand;
use MySqlMemory\Command\Maintenance\AdministrationCommand;
use MySqlMemory\Command\Maintenance\ChecksumCommand;
use MySqlMemory\Command\Show\Server\HelpCommand;
use MySqlMemory\Command\Show\Server\ShowCollationCommand;
use MySqlMemory\Command\Show\Server\ShowEnginesCommand;
use MySqlMemory\Command\Show\Server\ShowPluginsCommand;
use MySqlMemory\Command\Show\Server\ShowPrivilegesCommand;
use MySqlMemory\Command\Show\Server\ShowProfilesCommand;
use MySqlMemory\Command\Show\Server\ShowVariablesCommand;
use MySqlMemory\Command\Show\ShowColumnsCommand;
use MySqlMemory\Command\Show\ShowCreateDatabaseCommand;
use MySqlMemory\Command\Show\ShowCreateTableCommand;
use MySqlMemory\Command\Show\ShowKeysCommand;
use MySqlMemory\Command\Show\ShowOpenTablesCommand;
use MySqlMemory\Command\Show\ShowTableStatusCommand;
use MySqlMemory\Command\Write\ChangeCommand;
use MySqlMemory\Command\Write\InsertCommand;
use MySqlMemory\Command\Write\MultipleChangeCommand;
use MySqlMemory\Error\ErrorCode;
use ReflectionClass;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\DropIndex;
use SqlSemantics\Platform\MySql\Statement\Alter\DropTable;
use SqlSemantics\Platform\MySql\Statement\Alter\RenameTable;
use SqlSemantics\Platform\MySql\Statement\Alter\TruncateTable;
use SqlSemantics\Platform\MySql\Statement\Dml\Delete;
use SqlSemantics\Platform\MySql\Statement\Dml\Evaluation;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerClose;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexRead;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexSeek;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerOpen;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerScan;
use SqlSemantics\Platform\MySql\Statement\Dml\ImportTable;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertSet;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable;
use SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Server\Database\AlterDatabase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DropDatabase;
use SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CacheIndex;
use SqlSemantics\Platform\MySql\Statement\Server\KeyCache\LoadIndex;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\LockTables;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\UnlockTables;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\AnalyzeTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\ChecksumTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\CheckTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\OptimizeTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\RepairTable;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Begin;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Commit;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Rollback;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\StartTransaction;
use SqlSemantics\Platform\MySql\Statement\Table\CreateIndex;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTableLike;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\DescribeTable;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Help;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\UseDatabase;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowCreateDatabase;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowCreateTable;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowKeys;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowOpenTables;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTables;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTableStatus;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCharacterSet;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCollation;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineCatalog;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineLogs;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineMutex;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineStatus;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowPlugins;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowPrivileges;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowStatus;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowVariables;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowErrorCount;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowErrors;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowProfile;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowProfiles;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowWarningCount;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowWarnings;
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
     * Answers the command of a statement.
     *
     * @throws \MySqlMemory\Error\SqlError When no command executes the statement
     */
    public function command(Statement $statement): Command
    {
        return match (true) {
            $statement instanceof Query => new QueryCommand(),
            $statement instanceof InsertRows, $statement instanceof InsertSet, $statement instanceof InsertQuery => new View\ViewWriteCommand(new InsertCommand()),
            $statement instanceof Update && MultipleChangeCommand::joined($statement), $statement instanceof MultipleDelete => new MultipleChangeCommand(),
            $statement instanceof Update, $statement instanceof Delete => new View\ViewWriteCommand(new ChangeCommand()),
            $statement instanceof CreateTable => new CreateTableCommand(),
            $statement instanceof DropTable, $statement instanceof TruncateTable => new DropTableCommand(),
            $statement instanceof AlterTable, $statement instanceof CreateIndex, $statement instanceof DropIndex => new AlterTableCommand(),
            $statement instanceof RenameTable => new RenameTableCommand(),
            $statement instanceof CreateTableLike => new CreateTableLikeCommand(),
            $statement instanceof AlterDatabase => new AlterDatabaseCommand(),
            $statement instanceof LoadTable => new LoadDataCommand(),
            $statement instanceof ImportTable => new ImportTableCommand(),
            $statement instanceof LockTables, $statement instanceof UnlockTables => new LockTablesCommand(),
            $statement instanceof HandlerOpen, $statement instanceof HandlerClose, $statement instanceof HandlerScan, $statement instanceof HandlerIndexRead, $statement instanceof HandlerIndexSeek => new HandlerCommand(),
            $statement instanceof CreateDatabase, $statement instanceof DropDatabase, $statement instanceof UseDatabase => new DatabaseCommand(),
            $statement instanceof SetVariables => new SetCommand(),
            $statement instanceof Begin, $statement instanceof StartTransaction, $statement instanceof Commit, $statement instanceof Rollback => new TransactionCommand(),
            $statement instanceof ShowWarnings, $statement instanceof ShowErrors, $statement instanceof ShowWarningCount, $statement instanceof ShowErrorCount => new WarningsCommand(),
            $statement instanceof Evaluation => new DoCommand(),
            $statement instanceof ShowTables => new ShowTablesCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal => new Condition\SignalCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal => Condition\SignalCommand::resignal($statement),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\GetDiagnostics => new Condition\DiagnosticsCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Prepare, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Execute, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Deallocate => new Prepared\PreparedCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowProcedureCode, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowFunctionCode => new Program\ProgramCodeCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\AlterRoutine => new Program\RoutineCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger => new Program\TriggerCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateEvent, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\AlterEvent => new Program\EventCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\DropProgram => new Program\DropProgramCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall => new Program\CallCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowCreateProcedure, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowCreateFunction, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowCreateTrigger, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowCreateEvent => new Program\ShowCreateProgramCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowProcedureStatus, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowFunctionStatus => new Program\ShowRoutinesCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTriggers => new Program\ShowTriggersCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowEvents => new Program\ShowEventsCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\View\CreateView, $statement instanceof \SqlSemantics\Platform\MySql\Statement\View\AlterView => new View\ViewCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\View\DropView => new View\DropViewCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowCreateView => new View\ShowCreateViewCommand(),
            $statement instanceof ShowColumns, $statement instanceof DescribeTable => new ShowColumnsCommand(),
            $statement instanceof ShowKeys => new ShowKeysCommand(),
            $statement instanceof ShowTableStatus => new ShowTableStatusCommand(),
            $statement instanceof ShowOpenTables => new ShowOpenTablesCommand(),
            $statement instanceof ShowVariables, $statement instanceof ShowStatus => new ShowVariablesCommand(),
            $statement instanceof ShowCollation, $statement instanceof ShowCharacterSet => new ShowCollationCommand(),
            $statement instanceof ShowEngineCatalog, $statement instanceof ShowEngineLogs, $statement instanceof ShowEngineMutex, $statement instanceof ShowEngineStatus => new ShowEnginesCommand(),
            $statement instanceof ShowPlugins => new ShowPluginsCommand(),
            $statement instanceof ShowPrivileges => new ShowPrivilegesCommand(),
            $statement instanceof ShowProfiles, $statement instanceof ShowProfile => new ShowProfilesCommand(),
            $statement instanceof Help => new HelpCommand(),
            $statement instanceof Explain, $statement instanceof ExplainConnection => new ExplainCommand(),
            $statement instanceof ShowCreateTable => new ShowCreateTableCommand(),
            $statement instanceof ShowCreateDatabase => new ShowCreateDatabaseCommand(),
            $statement instanceof CheckTable, $statement instanceof OptimizeTable, $statement instanceof RepairTable, $statement instanceof AnalyzeTable, $statement instanceof CacheIndex, $statement instanceof LoadIndex => new AdministrationCommand(),
            $statement instanceof ChecksumTable => new ChecksumCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\CreateUser, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\CreateRole => new Account\CreateUserCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\DropUser, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\DropRole => new Account\DropUserCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\AlterUser => new Account\AlterUserCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\RenameUser => new Account\RenameUserCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\SetPassword => new Account\SetPasswordCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantRoles, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantProxy => new Account\GrantCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokePrivileges, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeRoles, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeProxy, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeAll => new Account\RevokeCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\SetRole, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\SetDefaultRole, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\AlterDefaultRole => new Account\RoleCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowGrants => new Account\ShowGrantsCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCreateUser => new Account\ShowCreateUserCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Savepoint, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Transaction\RollbackToSavepoint, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Transaction\ReleaseSavepoint => new Transaction\SavepointCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaStart, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaEnd, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaPrepare, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaCommit, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaRollback, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaRecover => new Transaction\XaCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\CreateResourceGroup, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\AlterResourceGroup, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\DropResourceGroup, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\SetResourceGroup => new Admin\ResourceGroupCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\CreateServer, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\AlterServer, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\DropServer => new Admin\ForeignServerCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Plugin\InstallPlugin, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallPlugin, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Plugin\InstallComponent, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallComponent => new Admin\PluginCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateTablespace, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateUndoTablespace, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespace, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceAccess, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceDatafile, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterUndoTablespace, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Storage\RenameTablespace, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Storage\DropTablespace, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Storage\DropUndoTablespace => new Admin\TablespaceCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateLogfileGroup, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterLogfileGroup, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Storage\DropLogfileGroup => new Admin\LogfileGroupCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Spatial\CreateSpatialReference, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Spatial\DropSpatialReference => new Admin\SpatialReferenceCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Instance\AlterInstance, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Lock\LockInstance, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Lock\UnlockInstance => new Admin\InstanceCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Flush\Flush, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushTables => new Admin\FlushCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\AlterRegistration => new Admin\RegistrationCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetPersist => new Admin\PersistCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Replication\Replica\StartReplica, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Replication\Replica\StopReplica, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Replication\Source\ChangeReplicationSource, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Replication\Filter\ChangeReplicationFilter, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Replication\Reset\Reset, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Replication\Group\StartGroupReplication, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Replication\Group\StopGroupReplication => new Replication\ReplicaCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsTo, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Replication\Log\BinlogEvent => new Replication\BinaryLogCommand(),
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowReplicaStatus, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowReplicas, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowBinaryLogs, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowBinaryLogStatus, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowBinlogEvents, $statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowRelaylogEvents => new Replication\ReplicationShowCommand(),
            default => throw ErrorCode::NotSupportedYet->error((new ReflectionClass($statement))->getShortName()),
        };
    }
}
