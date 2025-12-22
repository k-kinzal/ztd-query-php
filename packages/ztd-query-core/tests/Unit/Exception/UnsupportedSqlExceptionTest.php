<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZtdQuery\Exception\UnsupportedSqlException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UnsupportedSqlException::class)]
final class UnsupportedSqlExceptionTest extends TestCase
{
    public function testGetMessageReturnsFormattedMessage(): void
    {
        $exception = new UnsupportedSqlException(
            'GRANT SELECT ON users TO admin',
            'DCL'
        );

        self::assertSame('ZTD Write Protection: DCL SQL statement.', $exception->getMessage());
    }

    public function testGetMessageWithDefaultCategory(): void
    {
        $exception = new UnsupportedSqlException('SOME UNSUPPORTED SQL');

        self::assertSame('ZTD Write Protection: Unsupported SQL statement.', $exception->getMessage());
    }

    public function testGetSqlReturnsOriginalSql(): void
    {
        $sql = 'GRANT SELECT ON users TO admin';
        $exception = new UnsupportedSqlException($sql, 'DCL');

        self::assertSame($sql, $exception->getSql());
    }

    public function testGetCategoryReturnsCategory(): void
    {
        $exception = new UnsupportedSqlException('sql', 'DCL');

        self::assertSame('DCL', $exception->getCategory());
    }

    public function testGetCategoryReturnsDefaultCategory(): void
    {
        $exception = new UnsupportedSqlException('sql');

        self::assertSame('Unsupported', $exception->getCategory());
    }

    public function testExtendsRuntimeException(): void
    {
        $exception = new UnsupportedSqlException('sql');

        self::assertInstanceOf(RuntimeException::class, $exception);
    }
}
