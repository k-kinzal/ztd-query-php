<?php

declare(strict_types=1);

namespace Tests\Unit\Driver;

use mysqli;
use mysqli_result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;
use Tests\Container\MySql80Container;
use Tests\Container\MySql84Container;
use ZtdQuery\Adapter\Mysqli\Driver\MysqliResultColumnExtractor;
use ZtdQuery\Adapter\Mysqli\Driver\MysqliResultStatement;
use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(MysqliResultStatement::class)]
#[Large]
#[UsesClass(MysqliResultColumnExtractor::class)]
final class MysqliResultStatementTest extends TestCase
{
    public function testExecuteRepresentsAWriteWithoutAResultSet(): void
    {
        $statement = new MysqliResultStatement(null, 2);
        self::assertTrue($statement->execute());
        self::assertTrue($statement->execute([1, 2]));
        self::assertSame([], $statement->fetchAll());
        self::assertSame(2, $statement->rowCount());
        self::assertSame([], $statement->resultColumns(self::createStub(ResultColumnTypeResolver::class)));
    }

    public function testRowCountRetainsZeroAndLargeAffectedRowCounts(): void
    {
        self::assertSame(0, (new MysqliResultStatement(null, 0))->rowCount());
        self::assertSame(PHP_INT_MAX, (new MysqliResultStatement(null, '999999999999999999999999999999'))->rowCount());
    }

    public function testFetchAllReadsNativeRowsWithoutLosingColumnNames(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $connection = $container->getData(mysqli::class);
            $result = $connection->query("SELECT 1 AS id, 'Alice' AS name UNION ALL SELECT 2, 'Bob'");
            self::assertInstanceOf(mysqli_result::class, $result);
            $statement = new MysqliResultStatement($result, 2);
            self::assertSame([['id' => '1', 'name' => 'Alice'], ['id' => '2', 'name' => 'Bob']], $statement->fetchAll());
            self::assertSame(2, $statement->rowCount());
            $connection->close();
        } finally {
            $container->stop();
        }
    }

    public function testResultColumnsResolvesResultColumnTypes(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $connection = $container->getData(mysqli::class);
            $result = $connection->query("SELECT 1 AS id, 'Alice' AS name");
            self::assertInstanceOf(mysqli_result::class, $result);
            $resolver = self::createStub(ResultColumnTypeResolver::class);
            $resolver->method('resolve')->willReturnCallback(static fn (array $metadata): ColumnType =>
                $metadata['name'] === 'id' ? new ColumnType(ColumnTypeFamily::INTEGER, 'INT') : new ColumnType(ColumnTypeFamily::STRING, 'VARCHAR'));
            $columns = (new MysqliResultStatement($result, 1))->resultColumns($resolver);
            self::assertSame(['id', 'name'], array_column($columns, 'name'));
            self::assertSame(ColumnTypeFamily::INTEGER, $columns[0]->type->family);
            self::assertSame(ColumnTypeFamily::STRING, $columns[1]->type->family);
            $connection->close();
        } finally {
            $container->stop();
        }
    }

}
