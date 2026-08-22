<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Platform\MySql\MySqlSessionSqlModeReflector;

#[CoversClass(MySqlSessionSqlModeReflector::class)]
final class MySqlSessionSqlModeReflectorTest extends TestCase
{
    public function testReflectsTheCurrentSessionSqlMode(): void
    {
        $statement = self::createStub(StatementInterface::class);
        $statement->method('fetchAll')->willReturn([['ztd_sql_mode' => 'STRICT_TRANS_TABLES,ANSI_QUOTES']]);
        $connection = self::createMock(ConnectionInterface::class);
        $connection->expects(self::once())
            ->method('query')
            ->with('SELECT @@SESSION.sql_mode AS ztd_sql_mode')
            ->willReturn($statement);

        self::assertSame(
            'STRICT_TRANS_TABLES,ANSI_QUOTES',
            (new MySqlSessionSqlModeReflector($connection))->reflect(),
        );
    }

    public function testUsesTheDefaultModeWhenReflectionFails(): void
    {
        $connection = self::createStub(ConnectionInterface::class);
        $connection->method('query')->willReturn(false);

        self::assertSame('', (new MySqlSessionSqlModeReflector($connection))->reflect());
    }
}
