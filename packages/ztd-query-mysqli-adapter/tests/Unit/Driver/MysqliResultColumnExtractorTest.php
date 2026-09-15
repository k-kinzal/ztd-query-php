<?php

declare(strict_types=1);

namespace Tests\Unit\Driver;

use mysqli;
use mysqli_result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;
use Tests\Container\MySql80Container;
use Tests\Container\MySql84Container;
use ZtdQuery\Adapter\Mysqli\Driver\MysqliResultColumnExtractor;
use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(MysqliResultColumnExtractor::class)]
#[Large]
final class MysqliResultColumnExtractorTest extends TestCase
{
    public function testExtractPassesNativeFieldMetadataToTheTypeResolver(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $connection = $container->getData(mysqli::class);
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
        } finally {
            $container->stop();
        }
    }

    public function testExtractPreservesEveryColumnAndItsOrder(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $connection = $container->getData(mysqli::class);
            $result = $connection->query("SELECT 1 AS id, 'Alice' AS name");
            self::assertInstanceOf(mysqli_result::class, $result);
            $resolver = self::createStub(ResultColumnTypeResolver::class);
            $resolver->method('resolve')->willReturn(new ColumnType(ColumnTypeFamily::STRING, 'TEXT'));
            $columns = MysqliResultColumnExtractor::extract($result, $resolver);
            self::assertSame(['id', 'name'], array_column($columns, 'name'));
            $connection->close();
        } finally {
            $container->stop();
        }
    }

}
