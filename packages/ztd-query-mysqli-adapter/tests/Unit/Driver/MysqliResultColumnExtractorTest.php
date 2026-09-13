<?php

declare(strict_types=1);

namespace Tests\Unit;

use mysqli;
use mysqli_result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Mysqli\MysqliResultColumnExtractor;
use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(MysqliResultColumnExtractor::class)]
#[Large]
final class MysqliResultColumnExtractorTest extends TestCase
{
    public function testExtractPassesNativeFieldMetadataToTheTypeResolver(): void
    {
        $host = getenv('ZTD_TEST_MYSQL_HOST');
        $port = getenv('ZTD_TEST_MYSQL_PORT');
        self::assertIsString($host);
        self::assertIsString($port);
        $port = (int) $port;
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $result = $connection->query('SELECT CAST(7 AS SIGNED) AS value');
        self::assertInstanceOf(mysqli_result::class, $result);
        $resolver = self::createMock(ResultColumnTypeResolver::class);
        $resolver->expects(self::once())->method('resolve')->with(self::callback(
            static fn (array $metadata): bool => $metadata['name'] === 'value'
                && $metadata['type'] === MYSQLI_TYPE_LONGLONG && $metadata['charsetnr'] === 63,
        ))->willReturn(new ColumnType(ColumnTypeFamily::INTEGER, 'BIGINT'));
        $columns = MysqliResultColumnExtractor::extract($result, $resolver);
        self::assertSame('value', $columns[0]->name);
        self::assertSame(ColumnTypeFamily::INTEGER, $columns[0]->type->family);
        $connection->close();
    }

    public function testExtractPreservesEveryColumnAndItsOrder(): void
    {
        $host = getenv('ZTD_TEST_MYSQL_HOST');
        $port = getenv('ZTD_TEST_MYSQL_PORT');
        self::assertIsString($host);
        self::assertIsString($port);
        $port = (int) $port;
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $result = $connection->query("SELECT 1 AS id, 'Alice' AS name");
        self::assertInstanceOf(mysqli_result::class, $result);
        $resolver = self::createStub(ResultColumnTypeResolver::class);
        $resolver->method('resolve')->willReturn(new ColumnType(ColumnTypeFamily::STRING, 'TEXT'));
        $columns = MysqliResultColumnExtractor::extract($result, $resolver);
        self::assertSame(['id', 'name'], array_column($columns, 'name'));
        $connection->close();
    }

}
