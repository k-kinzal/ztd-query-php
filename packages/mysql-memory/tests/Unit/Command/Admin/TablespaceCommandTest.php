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
use PHPUnit\Framework\Attributes\DataProvider;
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
    /**
     * @param non-empty-list<int> $codes
     */
    #[DataProvider('providerLockedCommands')]
    public function testExecuteChecksTableLocksInReleaseOrder(string $version, string $lock, string $sql, array $codes): void
    {
        $session = (new Instance($version, databases: ['d']))->connect(database: 'd');
        $session->query("SET sql_mode='NO_ENGINE_SUBSTITUTION'; CREATE TABLE t(id INT); LOCK TABLE t " . $lock . '; ROLLBACK');
        try {
            $session->query($sql);
            self::fail('The storage command must refuse the locked session.');
        } catch (SqlError $error) {
            self::assertSame($codes[0], $error->getCode());
        }
        self::assertSame($codes, array_column($session->diagnostics->conditions, 1));
        self::assertCount(1, $session->locks);
        self::assertSame([], $session->instance->registry->tablespaces);
    }

    /**
     * @return iterable<string, array{string, string, string, non-empty-list<int>}>
     */
    public static function providerLockedCommands(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            foreach (['READ', 'WRITE'] as $lock) {
                foreach ([
                    "CREATE TABLESPACE ts ADD DATAFILE 'datafile'" => [$version === '5.6.51' ? 1478 : 1192],
                    "CREATE TABLESPACE ts ADD DATAFILE 'datafile' ENGINE=unknown_engine" => [1286],
                    "CREATE TABLESPACE ts ADD DATAFILE 'datafile' ENGINE=MyISAM" => [$version === '5.7.44' ? 1192 : 1478],
                    "ALTER TABLESPACE ts ADD DATAFILE 'another'" => [$version === '5.6.51' ? 1478 : 1192],
                    'DROP TABLESPACE ts' => [$version === '5.6.51' ? 1478 : 1192],
                    "CREATE UNDO TABLESPACE undo_ts ADD DATAFILE 'undo_datafile'" => [str_starts_with($version, '5.') ? 1064 : 1192],
                    'ALTER UNDO TABLESPACE undo_ts SET INACTIVE' => [str_starts_with($version, '5.') ? 1064 : 1192],
                    'DROP UNDO TABLESPACE undo_ts' => [str_starts_with($version, '5.') ? 1064 : 1192],
                ] as $sql => $codes) {
                    yield $version . ':' . $lock . ':' . $sql => [$version, $lock, $sql, $codes];
                }
            }
        }
    }

    public function testStoredEngineRetainsTheMatchingEngineWarningOnEarlierReleases(): void
    {
        $session = (new Instance('8.0.44'))->connect();
        $session->query("CREATE TABLESPACE t ADD DATAFILE 't.ibd'");
        $reply = $session->query('ALTER TABLESPACE t ENGINE innodb')[0];
        self::assertInstanceOf(\MySqlMemory\Result\Completion::class, $reply);
        self::assertSame(1, $reply->warnings);
        self::assertSame([['Warning', 1681, "'ENGINE tablespace option' is deprecated and will be removed in a future release."]], $session->diagnostics->conditions);
    }

    public function testStoredEngineRefusesAnExistingTablespacesDifferentEngine(): void
    {
        $session = (new Instance('8.3.0'))->connect();
        $session->query("CREATE TABLESPACE t ADD DATAFILE 't.ibd'");
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3642);
        $this->expectExceptionMessage("Engine 'missing' does not match stored engine 'InnoDB' for tablespace 't'");
        $session->query('DROP TABLESPACE t ENGINE missing');
    }

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
        $statement = (new Instance())->connect()->analyze('CREATE TABLESPACE mysql_x');
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
        $statement = $session->analyze("CREATE UNDO TABLESPACE v ADD DATAFILE 'u.ibu'");
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
        $statement = $session->analyze('ALTER TABLESPACE t RENAME TO mysql');
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
        $statement = $session->analyze("ALTER TABLESPACE t ENCRYPTION = 'X'");
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
        $statement = $session->analyze("ALTER TABLESPACE t DROP DATAFILE 'x.ibd'");
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
        $statement = $session->analyze('ALTER UNDO TABLESPACE t SET INACTIVE');
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
        $statement = $session->analyze('DROP TABLESPACE t ENGINE=InnoDB');
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
        $statement = (new Instance())->connect()->analyze('DROP UNDO TABLESPACE u STORAGE ENGINE PROXY');
        self::assertInstanceOf(DropUndoTablespace::class, $statement);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1286);
        $this->expectExceptionMessage("Unknown storage engine 'PROXY'");

        (new TablespaceCommand())->dropUndo($statement, new Registry());
    }

    public function testEngineNamesTheEngineAnAliasStandsFor(): void
    {
        $statement = (new Instance())->connect()->analyze('CREATE TABLESPACE t ENGINE = heap');
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
        $statement = (new Instance())->connect()->analyze('CREATE TABLESPACE t FILE_BLOCK_SIZE = 16K INITIAL_SIZE = 1024');
        self::assertInstanceOf(CreateTablespace::class, $statement);
        $first = $statement->options[0];
        $second = $statement->options[1];
        self::assertInstanceOf(SizeOption::class, $first);
        self::assertInstanceOf(SizeOption::class, $second);

        self::assertSame(['16384', '1024'], [(new TablespaceCommand())->size($first), (new TablespaceCommand())->size($second)]);
    }

    public function testActivateRefusesTheEngineBeforeTheTablespace(): void
    {
        $session = (new Instance())->connect();
        $unknown = $session->run('ALTER UNDO TABLESPACE nope SET INACTIVE ENGINE nope')[0];
        $other = $session->run('ALTER UNDO TABLESPACE nope SET INACTIVE ENGINE MyISAM')[0];
        $missing = $session->run('ALTER UNDO TABLESPACE nope SET INACTIVE ENGINE InnoDB')[0];

        self::assertInstanceOf(SqlError::class, $unknown);
        self::assertInstanceOf(SqlError::class, $other);
        self::assertInstanceOf(SqlError::class, $missing);
        self::assertSame([[1286, "Unknown storage engine 'nope'"], [1478, "Table storage engine 'MyISAM' does not support the create option 'ALTER UNDO TABLESPACE'"], [3510, "Tablespace nope doesn't exist."]], [[$unknown->getCode(), $unknown->getMessage()], [$other->getCode(), $other->getMessage()], [$missing->getCode(), $missing->getMessage()]]);
    }

    /**
     * @param list<int> $codes
     */
    #[DataProvider('providerSubstitutedEngines')]
    public function testEngineSubstitutionPrecedesTableLocks(string $version, string $mode, string $default, string $sql, array $codes): void
    {
        $session = (new Instance($version, databases: ['d']))->connect(database: 'd');
        $session->query("SET sql_mode='" . $mode . "'; SET default_storage_engine='" . $default . "'; CREATE TABLE t(id INT); LOCK TABLE t WRITE");
        $error = $session->run($sql)[0];

        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame($codes, array_column($session->diagnostics->conditions, 1));
        self::assertSame($codes[count($codes) - 1], $error->getCode());
    }

    /**
     * @return iterable<string, array{string, string, string, string, list<int>}>
     */
    public static function providerSubstitutedEngines(): iterable
    {
        foreach (['8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            foreach (['', 'NO_ENGINE_SUBSTITUTION'] as $mode) {
                foreach (['InnoDB', 'MyISAM'] as $default) {
                    foreach (['', ' ENGINE=unknown_engine', ' ENGINE=InnoDB'] as $engine) {
                        $unknown = $engine === ' ENGINE=unknown_engine';
                        $failure = $default === 'InnoDB' || $engine === ' ENGINE=InnoDB' ? 1192 : 1478;
                        $codes = $unknown ? ($mode === '' ? [1286, 1266, $failure] : [1286]) : [$failure];
                        foreach (["CREATE TABLESPACE ts ADD DATAFILE 'datafile'", "CREATE UNDO TABLESPACE ts ADD DATAFILE 'datafile'", 'ALTER UNDO TABLESPACE ts SET INACTIVE', 'DROP UNDO TABLESPACE ts'] as $command) {
                            yield $version . ':' . $mode . ':' . $default . ':' . $command . $engine => [$version, $mode, $default, $command . $engine, $codes];
                        }
                    }
                }
            }
        }
    }
}
