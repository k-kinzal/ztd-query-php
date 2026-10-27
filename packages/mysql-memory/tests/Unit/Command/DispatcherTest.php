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
use MySqlMemory\Command\Admin\FlushCommand;
use MySqlMemory\Command\Admin\ForeignServerCommand;
use MySqlMemory\Command\Admin\InstanceCommand;
use MySqlMemory\Command\Admin\LogfileGroupCommand;
use MySqlMemory\Command\Admin\PersistCommand;
use MySqlMemory\Command\Admin\PluginCommand;
use MySqlMemory\Command\Admin\RegistrationCommand;
use MySqlMemory\Command\Admin\ResourceGroupCommand;
use MySqlMemory\Command\Admin\SpatialReferenceCommand;
use MySqlMemory\Command\Admin\TablespaceCommand;
use MySqlMemory\Command\DatabaseCommand;
use MySqlMemory\Command\Definition\AlterTableCommand;
use MySqlMemory\Command\Definition\CreateTableCommand;
use MySqlMemory\Command\Definition\CreateTableLikeCommand;
use MySqlMemory\Command\Definition\Database\AlterDatabaseCommand;
use MySqlMemory\Command\Definition\DropTableCommand;
use MySqlMemory\Command\Definition\RenameTableCommand;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Command\DoCommand;
use MySqlMemory\Command\EmptyCommand;
use MySqlMemory\Command\Maintenance\AdministrationCommand;
use MySqlMemory\Command\Maintenance\ChecksumCommand;
use MySqlMemory\Command\QueryCommand;
use MySqlMemory\Command\Replication\BinaryLogCommand;
use MySqlMemory\Command\Replication\ReplicaCommand;
use MySqlMemory\Command\Replication\ReplicationShowCommand;
use MySqlMemory\Command\SetCommand;
use MySqlMemory\Command\ShowTablesCommand;
use MySqlMemory\Command\Transaction\SavepointCommand;
use MySqlMemory\Command\Transaction\XaCommand;
use MySqlMemory\Command\TransactionCommand;
use MySqlMemory\Command\View\ViewWriteCommand;
use MySqlMemory\Command\WarningsCommand;
use MySqlMemory\Command\Write\ChangeCommand;
use MySqlMemory\Command\Write\InsertCommand;
use MySqlMemory\Command\Write\MultipleChangeCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Rules\SemanticCatalogue;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\Delete;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertSet;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Script;

#[CoversClass(Dispatcher::class)]
#[Small]
final class DispatcherTest extends TestCase
{
    public function testCommandChoosesTheCommandOfEachKindOfQueryAndWrite(): void
    {
        $node = static function (\SqlSemantics\Statement\AnalyzedStatement $analyzed): \SqlSemantics\Statement\Statement {
            $statement = $analyzed instanceof Statement ? $analyzed : $analyzed;
            return $statement;
        };
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); CREATE TABLE u (a INT)');
        $dispatcher = new Dispatcher();
        $insert = $dispatcher->command($node($session->analyze('INSERT INTO t VALUES (1)')));
        $update = $dispatcher->command($node($session->analyze('UPDATE t SET a = 1')));

        self::assertSame(
            [QueryCommand::class, QueryCommand::class, ViewWriteCommand::class, ViewWriteCommand::class, ViewWriteCommand::class, ViewWriteCommand::class, ViewWriteCommand::class, MultipleChangeCommand::class, MultipleChangeCommand::class],
            [
                $dispatcher->command($node($session->analyze('SELECT 1')))::class,
                $dispatcher->command($node($session->analyze('VALUES ROW(1)')))::class,
                $insert::class,
                $dispatcher->command($node($session->analyze('INSERT INTO t SET a = 1')))::class,
                $dispatcher->command($node($session->analyze('INSERT INTO t SELECT 1')))::class,
                $update::class,
                $dispatcher->command($node($session->analyze('DELETE FROM t')))::class,
                $dispatcher->command($node($session->analyze('UPDATE t, u SET t.a = 1')))::class,
                $dispatcher->command($node($session->analyze('DELETE t FROM t JOIN u')))::class,
            ],
        );
        self::assertInstanceOf(ViewWriteCommand::class, $insert);
        self::assertInstanceOf(ViewWriteCommand::class, $update);
        self::assertSame([InsertCommand::class, ChangeCommand::class], [$insert->command::class, $update->command::class]);
    }

    public function testCommandChoosesTheCommandOfEachKindOfDefinitionAndUtility(): void
    {
        $node = static function (\SqlSemantics\Statement\AnalyzedStatement $analyzed): \SqlSemantics\Statement\Statement {
            $statement = $analyzed instanceof Statement ? $analyzed : $analyzed;
            return $statement;
        };
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $dispatcher = new Dispatcher();

        self::assertSame(
            [CreateTableCommand::class, DropTableCommand::class, DropTableCommand::class, DatabaseCommand::class, DatabaseCommand::class, DatabaseCommand::class, SetCommand::class, TransactionCommand::class, TransactionCommand::class, TransactionCommand::class, TransactionCommand::class, WarningsCommand::class, WarningsCommand::class, WarningsCommand::class, WarningsCommand::class, DoCommand::class, ShowTablesCommand::class],
            [
                $dispatcher->command($node($session->analyze('CREATE TABLE v (a INT)')))::class,
                $dispatcher->command($node($session->analyze('DROP TABLE t')))::class,
                $dispatcher->command($node($session->analyze('TRUNCATE TABLE t')))::class,
                $dispatcher->command($node($session->analyze('CREATE DATABASE e')))::class,
                $dispatcher->command($node($session->analyze('DROP DATABASE d')))::class,
                $dispatcher->command($node($session->analyze('USE d')))::class,
                $dispatcher->command($node($session->analyze('SET @a = 1')))::class,
                $dispatcher->command($node($session->analyze('BEGIN')))::class,
                $dispatcher->command($node($session->analyze('START TRANSACTION')))::class,
                $dispatcher->command($node($session->analyze('COMMIT')))::class,
                $dispatcher->command($node($session->analyze('ROLLBACK')))::class,
                $dispatcher->command($node($session->analyze('SHOW WARNINGS')))::class,
                $dispatcher->command($node($session->analyze('SHOW ERRORS')))::class,
                $dispatcher->command($node($session->analyze('SHOW COUNT(*) WARNINGS')))::class,
                $dispatcher->command($node($session->analyze('SHOW COUNT(*) ERRORS')))::class,
                $dispatcher->command($node($session->analyze('DO 1')))::class,
                $dispatcher->command($node($session->analyze('SHOW TABLES')))::class,
            ],
        );
    }

    public function testCommandChoosesTheCommandOfEachKindOfAccountStatement(): void
    {
        $node = static function (\SqlSemantics\Statement\AnalyzedStatement $analyzed): \SqlSemantics\Statement\Statement {
            $statement = $analyzed instanceof Statement ? $analyzed : $analyzed;
            return $statement;
        };
        $session = (new Instance())->connect();
        $dispatcher = new Dispatcher();

        self::assertSame(
            [CreateUserCommand::class, \MySqlMemory\Command\Account\Role\CreateRoleCommand::class, DropUserCommand::class, DropUserCommand::class, AlterUserCommand::class, RenameUserCommand::class, SetPasswordCommand::class, GrantCommand::class, \MySqlMemory\Command\Account\Role\GrantRolesCommand::class, GrantCommand::class, RevokeCommand::class, RevokeCommand::class, RevokeCommand::class, RevokeCommand::class, RoleCommand::class, RoleCommand::class, RoleCommand::class, ShowGrantsCommand::class, ShowCreateUserCommand::class],
            [
                $dispatcher->command($node($session->analyze('CREATE USER u')))::class,
                $dispatcher->command($node($session->analyze('CREATE ROLE r')))::class,
                $dispatcher->command($node($session->analyze('DROP USER u')))::class,
                $dispatcher->command($node($session->analyze('DROP ROLE r')))::class,
                $dispatcher->command($node($session->analyze('ALTER USER u ACCOUNT LOCK')))::class,
                $dispatcher->command($node($session->analyze('RENAME USER u TO v')))::class,
                $dispatcher->command($node($session->analyze("SET PASSWORD = 'x'")))::class,
                $dispatcher->command($node($session->analyze('GRANT SELECT ON *.* TO u')))::class,
                $dispatcher->command($node($session->analyze('GRANT r TO u')))::class,
                $dispatcher->command($node($session->analyze('GRANT PROXY ON v TO u')))::class,
                $dispatcher->command($node($session->analyze('REVOKE SELECT ON *.* FROM u')))::class,
                $dispatcher->command($node($session->analyze('REVOKE r FROM u')))::class,
                $dispatcher->command($node($session->analyze('REVOKE PROXY ON v FROM u')))::class,
                $dispatcher->command($node($session->analyze('REVOKE ALL, GRANT OPTION FROM u')))::class,
                $dispatcher->command($node($session->analyze('SET ROLE NONE')))::class,
                $dispatcher->command($node($session->analyze('SET DEFAULT ROLE NONE TO u')))::class,
                $dispatcher->command($node($session->analyze('ALTER USER u DEFAULT ROLE NONE')))::class,
                $dispatcher->command($node($session->analyze('SHOW GRANTS')))::class,
                $dispatcher->command($node($session->analyze('SHOW CREATE USER u')))::class,
            ],
        );
    }

    public function testCommandChoosesTheCommandOfEachKindOfTableChangeMaintenanceAndAccess(): void
    {
        $node = static function (\SqlSemantics\Statement\AnalyzedStatement $analyzed): \SqlSemantics\Statement\Statement {
            $statement = $analyzed instanceof Statement ? $analyzed : $analyzed;
            return $statement;
        };
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $dispatcher = new Dispatcher();

        self::assertSame(
            [AlterTableCommand::class, AlterTableCommand::class, AlterTableCommand::class, RenameTableCommand::class, CreateTableLikeCommand::class, AlterDatabaseCommand::class, AdministrationCommand::class, ChecksumCommand::class, LockTablesCommand::class, LockTablesCommand::class, HandlerCommand::class, LoadDataCommand::class, ImportTableCommand::class],
            [
                $dispatcher->command($node($session->analyze('ALTER TABLE t ADD b INT')))::class,
                $dispatcher->command($node($session->analyze('CREATE INDEX i ON t (a)')))::class,
                $dispatcher->command($node($session->analyze('DROP INDEX i ON t')))::class,
                $dispatcher->command($node($session->analyze('RENAME TABLE t TO u')))::class,
                $dispatcher->command($node($session->analyze('CREATE TABLE u LIKE t')))::class,
                $dispatcher->command($node($session->analyze('ALTER DATABASE d CHARACTER SET latin1')))::class,
                $dispatcher->command($node($session->analyze('CHECK TABLE t')))::class,
                $dispatcher->command($node($session->analyze('CHECKSUM TABLE t')))::class,
                $dispatcher->command($node($session->analyze('LOCK TABLES t READ')))::class,
                $dispatcher->command($node($session->analyze('UNLOCK TABLES')))::class,
                $dispatcher->command($node($session->analyze('HANDLER t OPEN')))::class,
                $dispatcher->command($node($session->analyze("LOAD DATA INFILE 'x' INTO TABLE t")))::class,
                $dispatcher->command($node($session->analyze("IMPORT TABLE FROM 'x'")))::class,
            ],
        );
    }

    public function testCommandRefusesAStatementNoCommandExecutes(): void
    {
        $node = static function (\SqlSemantics\Statement\AnalyzedStatement $analyzed): \SqlSemantics\Statement\Statement {
            $statement = $analyzed instanceof Statement ? $analyzed : $analyzed;
            return $statement;
        };
        $session = (new Instance())->connect();
        $statement = new Script([$node($session->analyze('SELECT 1')), $node($session->analyze('SELECT 2'))]);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1235);
        $this->expectExceptionMessage("This version of MySQL doesn't yet support 'Script'");

        (new Dispatcher())->command($statement);
    }

    public function testCommandCompletesAnEmptyStatement(): void
    {
        self::assertInstanceOf(EmptyCommand::class, (new Dispatcher())->command(new \SqlSemantics\Platform\MySql\Statement\EmptyStatement()));
    }

    public function testCommandChoosesTheCommandsOfServerAdministration(): void
    {
        $node = static function (\SqlSemantics\Statement\AnalyzedStatement $analyzed): \SqlSemantics\Statement\Statement {
            $statement = $analyzed instanceof Statement ? $analyzed : $analyzed;
            return $statement;
        };
        $session = (new Instance())->connect();
        $dispatcher = new Dispatcher();

        self::assertSame(
            [
                SavepointCommand::class,
                XaCommand::class,
                ResourceGroupCommand::class,
                ForeignServerCommand::class,
                PluginCommand::class,
                TablespaceCommand::class,
                LogfileGroupCommand::class,
                SpatialReferenceCommand::class,
                InstanceCommand::class,
                FlushCommand::class,
                RegistrationCommand::class,
                PersistCommand::class,
                ReplicaCommand::class,
                BinaryLogCommand::class,
                ReplicationShowCommand::class,
            ],
            [
                $dispatcher->command($node($session->analyze('SAVEPOINT s')))::class,
                $dispatcher->command($node($session->analyze('XA RECOVER')))::class,
                $dispatcher->command($node($session->analyze('SET RESOURCE GROUP g')))::class,
                $dispatcher->command($node($session->analyze('DROP SERVER s')))::class,
                $dispatcher->command($node($session->analyze('UNINSTALL PLUGIN p')))::class,
                $dispatcher->command($node($session->analyze('DROP TABLESPACE t')))::class,
                $dispatcher->command($node($session->analyze('DROP LOGFILE GROUP g')))::class,
                $dispatcher->command($node($session->analyze('DROP SPATIAL REFERENCE SYSTEM 5')))::class,
                $dispatcher->command($node($session->analyze('UNLOCK INSTANCE')))::class,
                $dispatcher->command($node($session->analyze('FLUSH STATUS')))::class,
                $dispatcher->command($node($session->analyze('ALTER USER u 2 FACTOR UNREGISTER')))::class,
                $dispatcher->command($node($session->analyze('RESET PERSIST')))::class,
                $dispatcher->command($node($session->analyze('STOP REPLICA')))::class,
                $dispatcher->command($node($session->analyze("BINLOG 'x'")))::class,
                $dispatcher->command($node($session->analyze('SHOW BINARY LOGS')))::class,
            ],
        );
    }

    public function testHandlesTellsWhetherAProgramDeclaresAHandler(): void
    {
        $node = static function (\SqlSemantics\Statement\AnalyzedStatement $analyzed): \SqlSemantics\Statement\Statement {
            $statement = $analyzed instanceof Statement ? $analyzed : $analyzed;
            return $statement;
        };
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame([true, false], [
            Dispatcher::handles($node($semantics->analyze('CREATE PROCEDURE p() BEGIN BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET @x = 1; END; END'))),
            Dispatcher::handles($node($semantics->analyze('CREATE PROCEDURE p() BEGIN SET @x = 1; END'))),
        ]);
    }

    public function testProgramTellsWhetherAStatementCreatesAStoredProgram(): void
    {
        $node = static function (\SqlSemantics\Statement\AnalyzedStatement $analyzed): \SqlSemantics\Statement\Statement {
            $statement = $analyzed instanceof Statement ? $analyzed : $analyzed;
            return $statement;
        };
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame([true, false], [Dispatcher::program($node($semantics->analyze('CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO SET @x = 1'))), Dispatcher::program($node($semantics->analyze('SELECT 1')))]);
    }

    public function testHandlesKeepsDiagnosticsForANamedConditionDeclaration(): void
    {
        $node = static function (\SqlSemantics\Statement\AnalyzedStatement $analyzed): \SqlSemantics\Statement\Statement {
            $statement = $analyzed instanceof Statement ? $analyzed : $analyzed;
            return $statement;
        };
        $session = (new Instance())->connect();
        $statement = $node($session->analyze('CREATE PROCEDURE d.p() BEGIN BEGIN DECLARE c CONDITION FOR 1; END; END'));

        self::assertTrue(Dispatcher::handles($statement));
        self::assertFalse((new Dispatcher())->command($statement)->clearsDiagnostics());
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

    /**
     * @return iterable<class-string, array{class-string}>
     */
    public static function providerDispatchStatements(): iterable
    {
        foreach (array_keys((new SemanticCatalogue())->generators()) as $class) {
            if (class_exists($class) && is_a($class, Statement::class, true)) {
                yield $class => [$class];
            }
        }
    }

    /**
     * @param class-string $class The concrete statement type
     */
    #[DataProvider('providerDispatchStatements')]
    public function testCommandEveryStatementHasAnExplicitCommandRoute(string $class): void
    {
        $specialized = in_array($class, [InsertRows::class, InsertSet::class, InsertQuery::class, Update::class, Delete::class, Resignal::class], true);
        $command = Dispatcher::COMMANDS[$class] ?? null;
        self::assertTrue($specialized || $command !== null, 'No command consumes ' . $class);
    }
}
