<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Admin;

use MySqlMemory\Command\Admin\TablespaceCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Registry\Registry;
use MySqlMemory\Registry\Tablespace;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespace;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceDatafile;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterUndoTablespace;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateTablespace;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateUndoTablespace;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\DropTablespace;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\DropUndoTablespace;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\SizeOption;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\RenameTablespace;

#[CoversClass(TablespaceCommand::class)]
#[Small]
final class TablespaceCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new TablespaceCommand())->clearsDiagnostics());
    }

    public function testExecuteCreatesRenamesAndDropsATablespace(): void
    {
        $instance = new Instance();
        $session = $instance->connect();
        $session->query("CREATE TABLESPACE t1; CREATE TABLESPACE T1; ALTER TABLESPACE t1 RENAME TO t4; CREATE TABLESPACE t2 ADD DATAFILE 't2.ibd' ENGINE=InnoDB");
        $created = array_keys($instance->registry->tablespaces);
        $session->query('DROP TABLESPACE t4; DROP TABLESPACE T1; DROP TABLESPACE t2 WAIT');

        self::assertSame([['T1', 't4', 't2'], []], [$created, $instance->registry->tablespaces]);
    }

    public function testCreateRefusesAnExistingName(): void
    {
        $statement = (new Instance())->connect()->analyze('CREATE TABLESPACE mysql_x')->statement;
        self::assertInstanceOf(CreateTablespace::class, $statement);
        $registry = new Registry();
        $registry->tablespaces['mysql_x'] = new Tablespace('mysql_x', false, '');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1813);
        $this->expectExceptionMessage("Tablespace 'mysql_x' exists.");

        (new TablespaceCommand())->create($statement, $registry);
    }

    public function testCreateChecksTheEngineBeforeTheName(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1478);
        $this->expectExceptionMessage("Table storage engine 'MyISAM' does not support the create option 'CREATE TABLESPACE'");

        $session->query('CREATE TABLESPACE innodb_n ENGINE=MyISAM');
    }

    public function testCreateRefusesAFileBlockSizeInnoDbLacks(): void
    {
        $session = (new Instance())->connect();
        $session->run('CREATE TABLESPACE n FILE_BLOCK_SIZE = 3');
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Error', '1478', 'InnoDB does not support FILE_BLOCK_SIZE=3'], ['Error', '1528', 'Failed to create TABLESPACE n'], ['Error', '1031', "Table storage engine for 'n' doesn't have this option"]], $warnings->rows);
    }

    public function testCreateUndoNeedsAFileEndingWithIbu(): void
    {
        $session = (new Instance())->connect();
        $session->run("CREATE UNDO TABLESPACE u ADD DATAFILE 'text'");
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([
            ['Error', '3121', 'The ADD DATAFILE filepath does not have a proper filename.'],
            ['Error', '3121', "The ADD DATAFILE filepath must end with '.ibu'."],
            ['Error', '1528', 'Failed to create UNDO TABLESPACE u'],
            ['Error', '3121', "Incorrect File Name 'text'."],
        ], $warnings->rows);
    }

    public function testCreateUndoRefusesTheFileOfAnotherTablespace(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE UNDO TABLESPACE u ADD DATAFILE 'u.ibu'");
        $statement = $session->analyze("CREATE UNDO TABLESPACE v ADD DATAFILE 'u.ibu'")->statement;
        self::assertInstanceOf(CreateUndoTablespace::class, $statement);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3606);
        $this->expectExceptionMessage("Duplicate file name for tablespace 'v'");

        (new TablespaceCommand())->createUndo($statement, $session->instance->registry);
    }

    public function testRenameRefusesATakenNameBeforeAReservedOne(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE TABLESPACE t');
        $statement = $session->analyze('ALTER TABLESPACE t RENAME TO mysql')->statement;
        self::assertInstanceOf(RenameTablespace::class, $statement);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1813);
        $this->expectExceptionMessage("Tablespace 'mysql' exists.");

        (new TablespaceCommand())->rename($statement, $session->instance->registry);
    }

    public function testRenameRefusesAMissingTablespace(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3510);
        $this->expectExceptionMessage("Tablespace SESSION doesn't exist.");

        $session->query('ALTER TABLESPACE SESSION RENAME TO PERSIST');
    }

    public function testAlterChecksTheKeyringBeforeTheEncryption(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE TABLESPACE t');
        $statement = $session->analyze("ALTER TABLESPACE t ENCRYPTION = 'X'")->statement;
        self::assertInstanceOf(AlterTablespace::class, $statement);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3185);

        (new TablespaceCommand())->alter('t', $statement->options, $session->instance->registry);
    }

    public function testAlterRefusesAnAutoextendSizeThatIsNoMultipleOf4M(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE TABLESPACE t');
        $session->run('ALTER TABLESPACE t AUTOEXTEND_SIZE = 5M');
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Error', '4023', 'AUTOEXTEND_SIZE should be a multiple of 4M'], ['Error', '1533', 'Failed to alter: TABLESPACE t'], ['Error', '1030', "Got error 4023 - 'Unknown error 4023' from storage engine"]], $warnings->rows);
    }

    public function testDatafileRefusesAFileTheTablespaceLacks(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE TABLESPACE t');
        $statement = $session->analyze("ALTER TABLESPACE t DROP DATAFILE 'x.ibd'")->statement;
        self::assertInstanceOf(AlterTablespaceDatafile::class, $statement);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3629);
        $this->expectExceptionMessage("Tablespace 't' does not have a file named 'x.ibd'");

        (new TablespaceCommand())->datafile($statement, $session->instance->registry);
    }

    public function testDatafileCannotAddAFile(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE TABLESPACE t');
        $session->run("ALTER TABLESPACE t ADD DATAFILE 'x.ibd'");
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Error', '1533', 'Failed to alter: TABLESPACE t'], ['Error', '1178', "The storage engine for the table doesn't support ALTER TABLESPACE ... ADD DATAFILE"]], $warnings->rows);
    }

    public function testActivateRefusesAGeneralTablespace(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE TABLESPACE t');
        $statement = $session->analyze('ALTER UNDO TABLESPACE t SET INACTIVE')->statement;
        self::assertInstanceOf(AlterUndoTablespace::class, $statement);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3119);
        $this->expectExceptionMessage('Cannot ALTER UNDO TABLESPACE `t` because it is a general tablespace.  Please use ALTER TABLESPACE.');

        (new TablespaceCommand())->activate($statement, $session->instance->registry);
    }

    public function testDropRefusesAnEngineOptionOnAnExistingTablespace(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE TABLESPACE t');
        $statement = $session->analyze('DROP TABLESPACE t ENGINE=InnoDB')->statement;
        self::assertInstanceOf(DropTablespace::class, $statement);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1149);

        (new TablespaceCommand())->drop($statement, $session->instance->registry);
    }

    public function testDropRefusesAnUndoTablespace(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE UNDO TABLESPACE u ADD DATAFILE 'u.ibu'");
        $session->run('DROP TABLESPACE u');
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([
            ['Error', '3119', 'Cannot DROP TABLESPACE `u` because it is an undo tablespace.  Please use DROP UNDO TABLESPACE.'],
            ['Error', '1529', 'Failed to drop TABLESPACE u'],
            ['Error', '3655', 'DROP TABLEPSPACE operation is disallowed on u'],
        ], $warnings->rows);
    }

    public function testDropUndoNeedsAnInactiveTablespace(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE UNDO TABLESPACE u ADD DATAFILE 'u.ibu'");
        $session->run('DROP UNDO TABLESPACE u');
        $warnings = $session->query('SHOW WARNINGS')[0];
        $session->query('ALTER UNDO TABLESPACE u SET INACTIVE; DROP UNDO TABLESPACE u');

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Error', '1529', 'Failed to drop UNDO TABLESPACE u'], ['Error', '3120', 'Tablespace `u` is not empty.']], $warnings->rows);
        self::assertSame([], $session->instance->registry->tablespaces);
    }

    public function testDropUndoChecksTheEngineFirst(): void
    {
        $statement = (new Instance())->connect()->analyze('DROP UNDO TABLESPACE u STORAGE ENGINE PROXY')->statement;
        self::assertInstanceOf(DropUndoTablespace::class, $statement);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1286);
        $this->expectExceptionMessage("Unknown storage engine 'PROXY'");

        (new TablespaceCommand())->dropUndo($statement, new Registry());
    }

    public function testEngineNamesTheEngineAnAliasStandsFor(): void
    {
        $statement = (new Instance())->connect()->analyze('CREATE TABLESPACE t ENGINE = heap')->statement;
        self::assertInstanceOf(CreateTablespace::class, $statement);

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Table storage engine 'MEMORY' does not support the create option 'X'");

        (new TablespaceCommand())->engine($statement->options, 'X');
    }

    public function testReservedRefusesANameStartingWithInnodb(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3119);
        $this->expectExceptionMessage('InnoDB: Tablespace names starting with `innodb_` are reserved.');

        (new TablespaceCommand())->reserved('innodb_x');
    }

    public function testReservedRefusesAnEmptyName(): void
    {
        $session = (new Instance())->connect();
        $session->run('CREATE TABLESPACE ``');
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Error', '3119', 'Incorrect tablespace name ``'], ['Error', '3119', 'Incorrect tablespace name ``']], $warnings->rows);
    }

    public function testExistsKnowsTheTablespacesOfTheServer(): void
    {
        self::assertSame([true, true, false], [(new TablespaceCommand())->exists('mysql', new Registry()), (new TablespaceCommand())->exists('innodb_undo_001', new Registry()), (new TablespaceCommand())->exists('MYSQL', new Registry())]);
    }

    public function testFindRefusesATablespaceNoStatementCreated(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Tablespace FILE doesn't exist.");

        (new TablespaceCommand())->find('FILE', new Registry());
    }

    public function testFileRefusesADirectoryUnderTheDataDirectory(): void
    {
        $session = (new Instance())->connect();
        $session->run("CREATE TABLESPACE n ADD DATAFILE 'a/b.ibd'");
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([
            ['Error', '3121', 'The directory does not exist or is incorrect.'],
            ['Error', '3121', 'The DATAFILE location cannot be under the datadir.'],
            ['Error', '1528', 'Failed to create TABLESPACE n'],
            ['Error', '3121', "Incorrect File Name 'a/b.ibd'."],
        ], $warnings->rows);
    }

    public function testOptionsRefusesEncryptionWithoutKeyring(): void
    {
        $session = (new Instance())->connect();
        $session->run("CREATE TABLESPACE n ENCRYPTION='Y'");
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([
            ['Error', '3185', "Can't find master key from keyring, please check in the server log if a keyring is loaded and initialized successfully."],
            ['Error', '1528', 'Failed to create TABLESPACE n'],
            ['Error', '1030', "Got error 138 - 'Unsupported extension used for table' from storage engine"],
        ], $warnings->rows);
    }

    public function testSizeReadsTheMultiplier(): void
    {
        $statement = (new Instance())->connect()->analyze('CREATE TABLESPACE t FILE_BLOCK_SIZE = 16K INITIAL_SIZE = 1024')->statement;
        self::assertInstanceOf(CreateTablespace::class, $statement);
        $first = $statement->options[0];
        $second = $statement->options[1];
        self::assertInstanceOf(SizeOption::class, $first);
        self::assertInstanceOf(SizeOption::class, $second);

        self::assertSame(['16384', '1024'], [(new TablespaceCommand())->size($first), (new TablespaceCommand())->size($second)]);
    }
}
