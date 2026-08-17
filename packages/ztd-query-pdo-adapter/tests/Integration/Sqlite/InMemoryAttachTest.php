<?php

declare(strict_types=1);

namespace Tests\Integration\Sqlite;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

/**
 * @requires extension pdo_sqlite
 */
#[CoversNothing]
#[Large]
final class InMemoryAttachTest extends TestCase
{
    public function testInMemoryDatabaseIsAttachedToPhysicalConnection(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);

        $ztdPdo->exec("ATTACH DATABASE ':memory:' AS db2");
        $statement = $rawPdo->query('PRAGMA database_list');

        self::assertNotFalse($statement);
        self::assertContains('db2', array_column($statement->fetchAll(), 'name'));
    }

    public function testPersistentDatabaseAttachIsRejected(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);

        $this->expectException(\RuntimeException::class);

        $ztdPdo->exec("ATTACH 'test.sqlite' AS db2");
    }
}
