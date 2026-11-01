<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Access;

use MySqlMemory\Command\Access\LoadDataCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable;

#[CoversClass(LoadDataCommand::class)]
#[Small]
final class LoadDataCommandTest extends TestCase
{
    #[DataProvider('providerLockedDestinations')]
    public function testEntryChecksTheDestinationLockBeforeColumnsAndFiles(string $version, string $create, string $lock, string $sql, int $code): void
    {
        $session = (new Instance($version, ['local_infile' => 0], databases: ['test']))->connect(database: 'test');
        $session->query($create);
        $session->query('CREATE TABLE u(id INT)');
        $session->query($lock);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode($code);
        $session->query($sql);
    }

    /**
     * @return iterable<string, array{string, string, string, string, int}>
     */
    public static function providerLockedDestinations(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            $local = str_starts_with($version, '5.') ? 1148 : 3948;
            foreach ([
                ['LOCK TABLES t READ', "LOAD DATA INFILE 'text' INTO TABLE t", 1099],
                ['LOCK TABLES t READ', "LOAD XML INFILE 'text' INTO TABLE t", 1099],
                ['LOCK TABLES t READ', "LOAD DATA INFILE 'text' INTO TABLE t (missing)", 1099],
                ['LOCK TABLES t READ', "LOAD DATA INFILE 'text' INTO TABLE t SET id=ROW(1,2)", 1099],
                ['LOCK TABLES t READ', "LOAD DATA INFILE 'text' INTO TABLE t FIELDS ENCLOSED BY 'aa'", 1083],
                ['LOCK TABLES t READ', "LOAD DATA INFILE 'text' INTO TABLE t CHARACTER SET missing", 1115],
                ['LOCK TABLES t READ', "LOAD DATA LOCAL INFILE 'text' INTO TABLE t", $local],
                ['LOCK TABLES t READ', "LOAD DATA INFILE 'text' INTO TABLE t PARTITION (missing)", 1747],
                ['LOCK TABLES u WRITE', "LOAD DATA INFILE 'text' INTO TABLE t PARTITION (missing)", 1100],
                ['LOCK TABLES t AS a WRITE', "LOAD DATA INFILE 'text' INTO TABLE t", 1100],
                ['LOCK TABLES t WRITE', "LOAD DATA INFILE 'text' INTO TABLE missing.t", 1100],
                ['LOCK TABLES t WRITE', "LOAD DATA INFILE 'text' INTO TABLE t (missing)", 1054],
                ['LOCK TABLES t WRITE', "LOAD DATA INFILE 'text' INTO TABLE t", 1290],
            ] as [$lock, $sql, $code]) {
                yield $version . '/' . $lock . '/' . $sql => [$version, 'CREATE TABLE t(id INT)', $lock, $sql, $code];
            }
            yield $version . '/temporary destination' => [$version, 'CREATE TEMPORARY TABLE t(id INT)', 'LOCK TABLES t READ', "LOAD DATA INFILE 'text' INTO TABLE t", 1290];
        }
    }

    #[DataProvider('providerFileErrors')]
    public function testExecutePreservesTheReleaseSpecificFileStatMessage(string $version, string $prefix): void
    {
        $session = (new Instance($version, databases: ['test']))->connect(database: 'test');
        $session->query('CREATE TABLE t(id INT)');
        $session->query('LOCK TABLES t WRITE');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(13);
        $this->expectExceptionMessage("Can't get stat of '/var/lib/mysql-files/missing' (" . $prefix . '2 - No such file or directory)');
        $session->query("LOAD DATA INFILE '/var/lib/mysql-files/missing' INTO TABLE t");
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerFileErrors(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            yield $version => [$version, str_starts_with($version, '5.') ? 'Errcode: ' : 'OS errno '];
        }
    }

    public function testSeparatorBytesIncludesTheXmlRowTag(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze("LOAD XML INFILE 'x' INTO TABLE d.t ROWS IDENTIFIED BY '<猫>'");
        self::assertInstanceOf(LoadTable::class, $operation);
        self::assertSame(['xml:ROW' => '<猫>'], (new LoadDataCommand())->separatorBytes($operation));
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1049);
        try {
            (new LoadDataCommand())->prepare($operation, $session);
        } finally {
            self::assertSame([1638], array_column($session->diagnostics->conditions, 1));
        }
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function providerAlgorithms(): iterable
    {
        yield 'XML before local' => ["LOAD XML LOCAL INFILE 'f' IGNORE INTO TABLE d.t ALGORITHM = BULK", 1221, 'Incorrect usage of LOAD XML and BULK Algorithm'];
        yield 'local before ignore' => ["LOAD DATA LOCAL INFILE 'f' IGNORE INTO TABLE d.t ALGORITHM = BULK", 1221, 'Incorrect usage of LOAD DATA with BULK Algorithm and LOCAL client file'];
        yield 'ignore before engine' => ["LOAD DATA INFILE 'f' IGNORE INTO TABLE d.t ALGORITHM = BULK", 1221, 'Incorrect usage of LOAD DATA with BULK Algorithm and IGNORE clause'];
        yield 'database before conflicts' => ["LOAD XML LOCAL URL 'f' COUNT 2 IGNORE INTO TABLE t COMPRESSION='x' ALGORITHM = BULK", 1046, 'No database selected'];
    }

    #[DataProvider('providerAlgorithms')]
    public function testExecuteOrdersAlgorithmAndSessionErrors(string $sql, int $code, string $message): void
    {
        $session = (new Instance())->connect();
        $this->expectException(SqlError::class);
        $this->expectExceptionCode($code);
        $this->expectExceptionMessage($message);

        $session->query($sql);
    }

    public function testEntryChecksLocalInputBeforeOpeningTheDestination(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $operation = $session->analyze("LOAD DATA LOCAL INFILE 'f' INTO TABLE absent");
        self::assertInstanceOf(LoadTable::class, $operation);
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3948);
        (new LoadDataCommand())->entry($operation, $operation->statementInformation(), $session);
    }

    public function testOptionsRetainsTheReleaseSpecificBulkConditions(): void
    {
        $session = (new Instance('8.2.0', [], ['d']))->connect(database: 'd');
        $operation = $session->analyze("LOAD DATA INFILE 'f' INTO TABLE absent ALGORITHM=BULK");
        self::assertInstanceOf(LoadTable::class, $operation);
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1235);
        (new LoadDataCommand())->options($operation, $operation->statementInformation(), $session);
    }

    public function testBulkConditionsFollowTargetAndPrimaryKeyChanges(): void
    {
        $session = (new Instance('8.2.0'))->connect();
        $session->query('CREATE DATABASE d; USE d');
        $operation = $session->analyze("LOAD DATA INFILE 'text' INTO TABLE t ALGORITHM=BULK");
        self::assertInstanceOf(LoadTable::class, $operation);
        $command = new LoadDataCommand();

        self::assertSame([[1146, "Table 'd.t' doesn't exist"]], $command->bulkConditions($operation, $session));
        $session->query('CREATE TABLE t(a INT)');
        self::assertSame([[4130, "Table 'd/t' has no primary key."]], $command->bulkConditions($operation, $session));
        $session->query('ALTER TABLE t ADD PRIMARY KEY(a)');
        self::assertSame([[1290, 'The MySQL server is running with the --secure-file-priv option so it cannot execute this statement']], $command->bulkConditions($operation, $session));
        self::assertSame([], $command->bulkConditions($operation, (new Instance())->connect()));
    }

    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new LoadDataCommand())->clearsDiagnostics());
    }

    public function testAlgorithmRefusesSeveralFilesBeforeTheTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1221);
        $this->expectExceptionMessage('Incorrect usage of LOAD DATA without BULK Algorithm and multiple files');

        $session->query("LOAD DATA FROM LOCAL URL 'x' COUNT 1 INTO TABLE nope");
    }

    public function testPrepareRefusesLocalInputBeforePreparingAssignmentSubqueries(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a INT); CREATE TABLE two_columns(a INT,b INT)');
        $operation = $session->analyze("LOAD XML LOCAL INFILE 'x' INTO TABLE t SET a=1=ALL(SELECT * FROM two_columns)");
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3948);
        (new LoadDataCommand())->prepare($operation, $session);
    }

    public function testExecuteRefusesLocalData(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3948);

        $session->query("LOAD DATA LOCAL INFILE 'x' INTO TABLE nope");
    }

    public function testExecuteRefusesAnUnknownDatabase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1049);

        $session->query("LOAD DATA INFILE 'x' INTO TABLE abc.t");
    }

    public function testExecuteRefusesAFileOutsideTheSecureDirectory(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1290);
        $this->expectExceptionMessage('The MySQL server is running with the --secure-file-priv option so it cannot execute this statement');

        $session->query("LOAD XML INFILE 'x' INTO TABLE t");
    }

    public function testExecuteFindsNoFileInTheSecureDirectory(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(13);
        $this->expectExceptionMessage("Can't get stat of '/var/lib/mysql-files/x' (OS errno 2 - No such file or directory)");

        $session->query("LOAD DATA INFILE '/var/lib/mysql-files/x' INTO TABLE t");
    }

    public function testSeparatorsRefusesAnEnclosingOfTwoBytesBeforeTheTableInMySql57(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1083);

        $session->query("LOAD DATA INFILE 'x' INTO TABLE nosuch FIELDS ENCLOSED BY 'é'");
    }

    public function testExecuteChecksTheTableOfLocalDataInMySql56(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1146);

        $session->query("LOAD DATA LOCAL INFILE 'x' INTO TABLE nosuch");
    }

    public function testExecuteRefusesAnEnclosingSeparatorOfMoreThanOneByteBeforeTheTableInMySql84(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1083);

        $session->query("LOAD DATA INFILE '/tmp/x' INTO TABLE nowhere.t FIELDS ENCLOSED BY 'ab'");
    }

    public function testSeparatorsTakesATerminatorOfSeveralBytes(): void
    {
        $session = (new Instance('9.1.0'))->connect();
        $statement = $session->analyze("LOAD DATA INFILE '/tmp/x' INTO TABLE t FIELDS TERMINATED BY 'abc' ESCAPED BY X'5C'");
        self::assertInstanceOf(LoadTable::class, $statement);

        (new LoadDataCommand())->separators($statement);

        $this->addToAssertionCount(1);
    }
    public function testSeparatorBytesKeepsOnlyTheLastValueOfEachOption(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze("LOAD DATA INFILE 'x' INTO TABLE d.t FIELDS TERMINATED BY X'80' TERMINATED BY 'a' ENCLOSED BY 'x' OPTIONALLY ENCLOSED BY 'y' LINES STARTING BY B'10000000'");
        self::assertInstanceOf(LoadTable::class, $operation);

        self::assertSame(['field:TERMINATED' => 'a', 'field:ENCLOSED' => 'y', 'line:STARTING' => "\x80"], (new LoadDataCommand())->separatorBytes($operation));
    }

}
