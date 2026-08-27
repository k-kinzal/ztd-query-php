<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeConnection;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Platform\MySql\MySqlSessionSqlModeReflector;

#[CoversClass(MySqlSessionSqlModeReflector::class)]
final class MySqlSessionSqlModeReflectorTest extends TestCase
{
    public function testReflectsTheCurrentSessionSqlMode(): void
    {
        $connection = new FakeConnection([
            'SELECT @@SESSION.sql_mode AS ztd_sql_mode' => [
                ['ztd_sql_mode' => 'STRICT_TRANS_TABLES,ANSI_QUOTES'],
            ],
        ]);

        self::assertSame(
            'STRICT_TRANS_TABLES,ANSI_QUOTES',
            (new MySqlSessionSqlModeReflector($connection))->reflect(),
        );
        self::assertSame(['SELECT @@SESSION.sql_mode AS ztd_sql_mode'], $connection->queries);
    }

    public function testUsesTheDefaultModeWhenReflectionFails(): void
    {
        $connection = self::createStub(ConnectionInterface::class);
        $connection->method('query')->willReturn(false);

        self::assertSame('', (new MySqlSessionSqlModeReflector($connection))->reflect());
    }
}
