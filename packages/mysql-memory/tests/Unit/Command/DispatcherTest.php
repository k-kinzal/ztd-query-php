<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use MySqlMemory\Command\Access\HandlerCommand;
use MySqlMemory\Command\Access\ImportTableCommand;
use MySqlMemory\Command\Access\LoadDataCommand;
use MySqlMemory\Command\Access\LockTablesCommand;
use MySqlMemory\Command\Account\AlterUserCommand;
use MySqlMemory\Command\Account\CreateUserCommand;
use MySqlMemory\Command\Account\DropUserCommand;
use MySqlMemory\Command\Account\GrantCommand;
use MySqlMemory\Command\Account\RenameUserCommand;
use MySqlMemory\Command\Account\RevokeCommand;
use MySqlMemory\Command\Account\RoleCommand;
use MySqlMemory\Command\Account\SetPasswordCommand;
use MySqlMemory\Command\Account\ShowCreateUserCommand;
use MySqlMemory\Command\Account\ShowGrantsCommand;
use MySqlMemory\Command\DatabaseCommand;
use MySqlMemory\Command\Definition\AlterDatabaseCommand;
use MySqlMemory\Command\Definition\AlterTableCommand;
use MySqlMemory\Command\Definition\CreateTableCommand;
use MySqlMemory\Command\Definition\CreateTableLikeCommand;
use MySqlMemory\Command\Definition\DropTableCommand;
use MySqlMemory\Command\Definition\RenameTableCommand;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Command\DoCommand;
use MySqlMemory\Command\Maintenance\AdministrationCommand;
use MySqlMemory\Command\Maintenance\ChecksumCommand;
use MySqlMemory\Command\QueryCommand;
use MySqlMemory\Command\SetCommand;
use MySqlMemory\Command\ShowTablesCommand;
use MySqlMemory\Command\TransactionCommand;
use MySqlMemory\Command\View\ViewWriteCommand;
use MySqlMemory\Command\WarningsCommand;
use MySqlMemory\Command\Write\ChangeCommand;
use MySqlMemory\Command\Write\InsertCommand;
use MySqlMemory\Command\Write\MultipleChangeCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Dispatcher::class)]
#[Small]
final class DispatcherTest extends TestCase
{
    public function testCommandChoosesTheCommandOfEachKindOfQueryAndWrite(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); CREATE TABLE u (a INT)');
        $dispatcher = new Dispatcher();
        $insert = $dispatcher->command($session->analyze('INSERT INTO t VALUES (1)')->statement);
        $update = $dispatcher->command($session->analyze('UPDATE t SET a = 1')->statement);

        self::assertSame(
            [QueryCommand::class, QueryCommand::class, ViewWriteCommand::class, ViewWriteCommand::class, ViewWriteCommand::class, ViewWriteCommand::class, ViewWriteCommand::class, MultipleChangeCommand::class, MultipleChangeCommand::class],
            [
                $dispatcher->command($session->analyze('SELECT 1')->statement)::class,
                $dispatcher->command($session->analyze('VALUES ROW(1)')->statement)::class,
                $insert::class,
                $dispatcher->command($session->analyze('INSERT INTO t SET a = 1')->statement)::class,
                $dispatcher->command($session->analyze('INSERT INTO t SELECT 1')->statement)::class,
                $update::class,
                $dispatcher->command($session->analyze('DELETE FROM t')->statement)::class,
                $dispatcher->command($session->analyze('UPDATE t, u SET t.a = 1')->statement)::class,
                $dispatcher->command($session->analyze('DELETE t FROM t JOIN u')->statement)::class,
            ],
        );
        self::assertInstanceOf(ViewWriteCommand::class, $insert);
        self::assertInstanceOf(ViewWriteCommand::class, $update);
        self::assertSame([InsertCommand::class, ChangeCommand::class], [$insert->command::class, $update->command::class]);
    }

    public function testCommandChoosesTheCommandOfEachKindOfDefinitionAndUtility(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $dispatcher = new Dispatcher();

        self::assertSame(
            [CreateTableCommand::class, DropTableCommand::class, DropTableCommand::class, DatabaseCommand::class, DatabaseCommand::class, DatabaseCommand::class, SetCommand::class, TransactionCommand::class, TransactionCommand::class, TransactionCommand::class, TransactionCommand::class, WarningsCommand::class, WarningsCommand::class, WarningsCommand::class, WarningsCommand::class, DoCommand::class, ShowTablesCommand::class],
            [
                $dispatcher->command($session->analyze('CREATE TABLE v (a INT)')->statement)::class,
                $dispatcher->command($session->analyze('DROP TABLE t')->statement)::class,
                $dispatcher->command($session->analyze('TRUNCATE TABLE t')->statement)::class,
                $dispatcher->command($session->analyze('CREATE DATABASE e')->statement)::class,
                $dispatcher->command($session->analyze('DROP DATABASE d')->statement)::class,
                $dispatcher->command($session->analyze('USE d')->statement)::class,
                $dispatcher->command($session->analyze('SET @a = 1')->statement)::class,
                $dispatcher->command($session->analyze('BEGIN')->statement)::class,
                $dispatcher->command($session->analyze('START TRANSACTION')->statement)::class,
                $dispatcher->command($session->analyze('COMMIT')->statement)::class,
                $dispatcher->command($session->analyze('ROLLBACK')->statement)::class,
                $dispatcher->command($session->analyze('SHOW WARNINGS')->statement)::class,
                $dispatcher->command($session->analyze('SHOW ERRORS')->statement)::class,
                $dispatcher->command($session->analyze('SHOW COUNT(*) WARNINGS')->statement)::class,
                $dispatcher->command($session->analyze('SHOW COUNT(*) ERRORS')->statement)::class,
                $dispatcher->command($session->analyze('DO 1')->statement)::class,
                $dispatcher->command($session->analyze('SHOW TABLES')->statement)::class,
            ],
        );
    }

    public function testCommandChoosesTheCommandOfEachKindOfAccountStatement(): void
    {
        $session = (new Instance())->connect();
        $dispatcher = new Dispatcher();

        self::assertSame(
            [CreateUserCommand::class, CreateUserCommand::class, DropUserCommand::class, DropUserCommand::class, AlterUserCommand::class, RenameUserCommand::class, SetPasswordCommand::class, GrantCommand::class, GrantCommand::class, GrantCommand::class, RevokeCommand::class, RevokeCommand::class, RevokeCommand::class, RevokeCommand::class, RoleCommand::class, RoleCommand::class, RoleCommand::class, ShowGrantsCommand::class, ShowCreateUserCommand::class],
            [
                $dispatcher->command($session->analyze('CREATE USER u')->statement)::class,
                $dispatcher->command($session->analyze('CREATE ROLE r')->statement)::class,
                $dispatcher->command($session->analyze('DROP USER u')->statement)::class,
                $dispatcher->command($session->analyze('DROP ROLE r')->statement)::class,
                $dispatcher->command($session->analyze('ALTER USER u ACCOUNT LOCK')->statement)::class,
                $dispatcher->command($session->analyze('RENAME USER u TO v')->statement)::class,
                $dispatcher->command($session->analyze("SET PASSWORD = 'x'")->statement)::class,
                $dispatcher->command($session->analyze('GRANT SELECT ON *.* TO u')->statement)::class,
                $dispatcher->command($session->analyze('GRANT r TO u')->statement)::class,
                $dispatcher->command($session->analyze('GRANT PROXY ON v TO u')->statement)::class,
                $dispatcher->command($session->analyze('REVOKE SELECT ON *.* FROM u')->statement)::class,
                $dispatcher->command($session->analyze('REVOKE r FROM u')->statement)::class,
                $dispatcher->command($session->analyze('REVOKE PROXY ON v FROM u')->statement)::class,
                $dispatcher->command($session->analyze('REVOKE ALL, GRANT OPTION FROM u')->statement)::class,
                $dispatcher->command($session->analyze('SET ROLE NONE')->statement)::class,
                $dispatcher->command($session->analyze('SET DEFAULT ROLE NONE TO u')->statement)::class,
                $dispatcher->command($session->analyze('ALTER USER u DEFAULT ROLE NONE')->statement)::class,
                $dispatcher->command($session->analyze('SHOW GRANTS')->statement)::class,
                $dispatcher->command($session->analyze('SHOW CREATE USER u')->statement)::class,
            ],
        );
    }

    public function testCommandChoosesTheCommandOfEachKindOfTableChangeMaintenanceAndAccess(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $dispatcher = new Dispatcher();

        self::assertSame(
            [AlterTableCommand::class, AlterTableCommand::class, AlterTableCommand::class, RenameTableCommand::class, CreateTableLikeCommand::class, AlterDatabaseCommand::class, AdministrationCommand::class, ChecksumCommand::class, LockTablesCommand::class, LockTablesCommand::class, HandlerCommand::class, LoadDataCommand::class, ImportTableCommand::class],
            [
                $dispatcher->command($session->analyze('ALTER TABLE t ADD b INT')->statement)::class,
                $dispatcher->command($session->analyze('CREATE INDEX i ON t (a)')->statement)::class,
                $dispatcher->command($session->analyze('DROP INDEX i ON t')->statement)::class,
                $dispatcher->command($session->analyze('RENAME TABLE t TO u')->statement)::class,
                $dispatcher->command($session->analyze('CREATE TABLE u LIKE t')->statement)::class,
                $dispatcher->command($session->analyze('ALTER DATABASE d CHARACTER SET latin1')->statement)::class,
                $dispatcher->command($session->analyze('CHECK TABLE t')->statement)::class,
                $dispatcher->command($session->analyze('CHECKSUM TABLE t')->statement)::class,
                $dispatcher->command($session->analyze('LOCK TABLES t READ')->statement)::class,
                $dispatcher->command($session->analyze('UNLOCK TABLES')->statement)::class,
                $dispatcher->command($session->analyze('HANDLER t OPEN')->statement)::class,
                $dispatcher->command($session->analyze("LOAD DATA INFILE 'x' INTO TABLE t")->statement)::class,
                $dispatcher->command($session->analyze("IMPORT TABLE FROM 'x'")->statement)::class,
            ],
        );
    }

    public function testCommandRefusesAStatementNoCommandExecutes(): void
    {
        $statement = new \SqlSemantics\Statement\Script([]);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1235);
        $this->expectExceptionMessage("This version of MySQL doesn't yet support 'Script'");

        (new Dispatcher())->command($statement);
    }

    public function testCommandChoosesTheCommandsOfServerAdministration(): void
    {
        $session = (new Instance())->connect();
        $dispatcher = new Dispatcher();

        self::assertSame(
            [
                \MySqlMemory\Command\Transaction\SavepointCommand::class,
                \MySqlMemory\Command\Transaction\XaCommand::class,
                \MySqlMemory\Command\Admin\ResourceGroupCommand::class,
                \MySqlMemory\Command\Admin\ForeignServerCommand::class,
                \MySqlMemory\Command\Admin\PluginCommand::class,
                \MySqlMemory\Command\Admin\TablespaceCommand::class,
                \MySqlMemory\Command\Admin\LogfileGroupCommand::class,
                \MySqlMemory\Command\Admin\SpatialReferenceCommand::class,
                \MySqlMemory\Command\Admin\InstanceCommand::class,
                \MySqlMemory\Command\Admin\FlushCommand::class,
                \MySqlMemory\Command\Admin\RegistrationCommand::class,
                \MySqlMemory\Command\Admin\PersistCommand::class,
                \MySqlMemory\Command\Replication\ReplicaCommand::class,
                \MySqlMemory\Command\Replication\BinaryLogCommand::class,
                \MySqlMemory\Command\Replication\ReplicationShowCommand::class,
            ],
            [
                $dispatcher->command($session->analyze('SAVEPOINT s')->statement)::class,
                $dispatcher->command($session->analyze('XA RECOVER')->statement)::class,
                $dispatcher->command($session->analyze('SET RESOURCE GROUP g')->statement)::class,
                $dispatcher->command($session->analyze('DROP SERVER s')->statement)::class,
                $dispatcher->command($session->analyze('UNINSTALL PLUGIN p')->statement)::class,
                $dispatcher->command($session->analyze('DROP TABLESPACE t')->statement)::class,
                $dispatcher->command($session->analyze('DROP LOGFILE GROUP g')->statement)::class,
                $dispatcher->command($session->analyze('DROP SPATIAL REFERENCE SYSTEM 5')->statement)::class,
                $dispatcher->command($session->analyze('UNLOCK INSTANCE')->statement)::class,
                $dispatcher->command($session->analyze('FLUSH STATUS')->statement)::class,
                $dispatcher->command($session->analyze('ALTER USER u 2 FACTOR UNREGISTER')->statement)::class,
                $dispatcher->command($session->analyze('RESET PERSIST')->statement)::class,
                $dispatcher->command($session->analyze('STOP REPLICA')->statement)::class,
                $dispatcher->command($session->analyze("BINLOG 'x'")->statement)::class,
                $dispatcher->command($session->analyze('SHOW BINARY LOGS')->statement)::class,
            ],
        );
    }

    public function testHandlesTellsWhetherAProgramDeclaresAHandler(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);

        self::assertSame([true, false], [
            Dispatcher::handles($semantics->analyze('CREATE PROCEDURE p() BEGIN BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET @x = 1; END; END')->statement),
            Dispatcher::handles($semantics->analyze('CREATE PROCEDURE p() BEGIN SET @x = 1; END')->statement),
        ]);
    }

    public function testProgramTellsWhetherAStatementCreatesAStoredProgram(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);

        self::assertSame([true, false], [Dispatcher::program($semantics->analyze('CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO SET @x = 1')->statement), Dispatcher::program($semantics->analyze('SELECT 1')->statement)]);
    }

    public function testCommandOfAProgramDeclaringAHandlerKeepsTheDiagnosticsArea(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('SELECT 1/0');
        $session->query('CREATE PROCEDURE p() BEGIN DECLARE EXIT HANDLER FOR SQLEXCEPTION SET @x = 1; END');

        self::assertSame([['Warning', 1365, 'Division by 0']], $session->diagnostics->conditions);
    }
}
