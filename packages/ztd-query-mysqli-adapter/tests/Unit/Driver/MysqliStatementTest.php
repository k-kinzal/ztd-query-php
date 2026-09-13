<?php

declare(strict_types=1);

namespace Tests\Unit;

use mysqli;
use mysqli_result;
use mysqli_stmt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Mysqli\MysqliResultColumnExtractor;
use ZtdQuery\Adapter\Mysqli\MysqliStatement;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(MysqliStatement::class)]
#[Large]
#[UsesClass(MysqliResultColumnExtractor::class)]
final class MysqliStatementTest extends TestCase
{
    public function testExecutesExplicitParameters(): void
    {
        $host = getenv('ZTD_TEST_MYSQL_HOST');
        $port = getenv('ZTD_TEST_MYSQL_PORT');
        self::assertIsString($host);
        self::assertIsString($port);
        $port = (int) $port;
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $native = $connection->prepare('SELECT ? AS value');
        self::assertInstanceOf(mysqli_stmt::class, $native);
        $statement = new MysqliStatement($native, $connection);
        self::assertTrue($statement->execute(['Alice']));
        self::assertSame([['value' => 'Alice']], $statement->fetchAll());
        $connection->close();
    }

    public function testPreservesBindingsWithAnEmptyParameterArray(): void
    {
        $host = getenv('ZTD_TEST_MYSQL_HOST');
        $port = getenv('ZTD_TEST_MYSQL_PORT');
        self::assertIsString($host);
        self::assertIsString($port);
        $port = (int) $port;
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $native = $connection->prepare('SELECT ? AS value');
        self::assertInstanceOf(mysqli_stmt::class, $native);
        $value = 7;
        $native->bind_param('i', $value);
        $statement = new MysqliStatement($native, $connection);
        $value = 42;
        self::assertTrue($statement->execute([]));
        self::assertSame([['value' => 42]], $statement->fetchAll());
        $connection->close();
    }

    public function testResultColumnsKeepsTheResultAvailableAfterReadingMetadata(): void
    {
        $host = getenv('ZTD_TEST_MYSQL_HOST');
        $port = getenv('ZTD_TEST_MYSQL_PORT');
        self::assertIsString($host);
        self::assertIsString($port);
        $port = (int) $port;
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $native = $connection->prepare('SELECT 7 AS id UNION ALL SELECT 9 AS id');
        self::assertInstanceOf(mysqli_stmt::class, $native);
        $statement = new MysqliStatement($native, $connection);
        self::assertTrue($statement->execute());
        $resolver = self::createStub(ResultColumnTypeResolver::class);
        $resolver->method('resolve')->willReturn(new ColumnType(ColumnTypeFamily::INTEGER, 'INT'));
        self::assertSame('id', $statement->resultColumns($resolver)[0]->name);
        self::assertSame('id', $statement->resultColumns($resolver)[0]->name);
        self::assertSame(2, $statement->rowCount());
        self::assertSame([['id' => 7], ['id' => 9]], $statement->fetchAll());
        $status = $connection->query("SHOW SESSION STATUS LIKE 'Com_stmt_close'");
        self::assertInstanceOf(mysqli_result::class, $status);
        self::assertSame([['Variable_name' => 'Com_stmt_close', 'Value' => '1']], $status->fetch_all(MYSQLI_ASSOC));
        $connection->close();
    }

    public function testFetchAllReturnsNoRowsOrMetadataForAWrite(): void
    {
        $host = getenv('ZTD_TEST_MYSQL_HOST');
        $port = getenv('ZTD_TEST_MYSQL_PORT');
        self::assertIsString($host);
        self::assertIsString($port);
        $port = (int) $port;
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $connection->query('CREATE TEMPORARY TABLE counts (id INT)');
        $native = $connection->prepare('INSERT INTO counts VALUES (1), (2)');
        self::assertInstanceOf(mysqli_stmt::class, $native);
        $statement = new MysqliStatement($native, $connection);
        self::assertTrue($statement->execute());
        self::assertSame(2, $statement->rowCount());
        self::assertSame([], $statement->resultColumns(self::createStub(ResultColumnTypeResolver::class)));
        self::assertSame([], $statement->fetchAll());
        $status = $connection->query("SHOW SESSION STATUS LIKE 'Com_stmt_close'");
        self::assertInstanceOf(mysqli_result::class, $status);
        self::assertSame([['Variable_name' => 'Com_stmt_close', 'Value' => '1']], $status->fetch_all(MYSQLI_ASSOC));
        $connection->close();
    }

    public function testReportsNativeExecutionErrorsWithoutParameters(): void
    {
        $host = getenv('ZTD_TEST_MYSQL_HOST');
        $port = getenv('ZTD_TEST_MYSQL_PORT');
        self::assertIsString($host);
        self::assertIsString($port);
        $port = (int) $port;
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $connection->query('CREATE TEMPORARY TABLE duplicate_keys (id INT PRIMARY KEY)');
        $connection->query('INSERT INTO duplicate_keys VALUES (1)');
        $native = $connection->prepare('INSERT INTO duplicate_keys VALUES (1)');
        self::assertInstanceOf(mysqli_stmt::class, $native);
        mysqli_report(MYSQLI_REPORT_OFF);
        try {
            $this->expectException(DatabaseException::class);
            $this->expectExceptionCode(1062);
            (new MysqliStatement($native, $connection))->execute();
        } finally {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $connection->close();
        }
    }

    public function testReportsNativeExecutionErrorsWithParameters(): void
    {
        $host = getenv('ZTD_TEST_MYSQL_HOST');
        $port = getenv('ZTD_TEST_MYSQL_PORT');
        self::assertIsString($host);
        self::assertIsString($port);
        $port = (int) $port;
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $connection->query('CREATE TEMPORARY TABLE duplicate_keys (id INT PRIMARY KEY)');
        $connection->query('INSERT INTO duplicate_keys VALUES (1)');
        $native = $connection->prepare('INSERT INTO duplicate_keys VALUES (?)');
        self::assertInstanceOf(mysqli_stmt::class, $native);
        mysqli_report(MYSQLI_REPORT_OFF);
        try {
            $this->expectException(DatabaseException::class);
            $this->expectExceptionCode(1062);
            (new MysqliStatement($native, $connection))->execute([1]);
        } finally {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $connection->close();
        }
    }

    public function testRowCountReportsNativeAffectedRows(): void
    {
        $host = getenv('ZTD_TEST_MYSQL_HOST');
        $port = getenv('ZTD_TEST_MYSQL_PORT');
        self::assertIsString($host);
        self::assertIsString($port);
        $port = (int) $port;
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $connection->query('CREATE TEMPORARY TABLE row_counts (id INT)');
        $native = $connection->prepare('INSERT INTO row_counts VALUES (1), (2)');
        self::assertInstanceOf(mysqli_stmt::class, $native);
        $statement = new MysqliStatement($native, $connection);
        self::assertTrue($statement->execute());
        self::assertSame(2, $statement->rowCount());
        $connection->close();
    }
}
