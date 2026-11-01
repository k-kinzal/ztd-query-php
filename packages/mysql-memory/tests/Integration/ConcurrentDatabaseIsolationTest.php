<?php

declare(strict_types=1);

namespace Tests\Integration;

use MySqlMemory\Server\MemoryDatabase;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class ConcurrentDatabaseIsolationTest extends TestCase
{
    public function testConcurrentReleasesKeepTheirVersionAndTablesIndependent(): void
    {
        $legacy = MemoryDatabase::start('5.7.44', ['shop']);
        $current = MemoryDatabase::start('9.1.0', ['shop']);
        try {
            $first = new PDO($legacy->dsn('shop'), 'root', '');
            $second = new PDO($current->dsn('shop'), 'root', '');
            $first->exec('CREATE TABLE legacy_only (id INT)');
            $firstVersion = $first->query('SELECT VERSION()');
            $secondVersion = $second->query('SELECT VERSION()');
            $tables = $second->query('SHOW TABLES');
            self::assertNotFalse($firstVersion);
            self::assertNotFalse($secondVersion);
            self::assertNotFalse($tables);
            self::assertSame('5.7.44', $firstVersion->fetchColumn());
            self::assertSame('9.1.0', $secondVersion->fetchColumn());
            self::assertSame([], $tables->fetchAll(PDO::FETCH_NUM));
            self::assertNotSame($legacy->port, $current->port);
        } finally {
            $legacy->stop();
            $current->stop();
        }
    }

}
