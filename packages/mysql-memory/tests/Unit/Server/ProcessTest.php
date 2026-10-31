<?php

declare(strict_types=1);

namespace Tests\Unit\Server;

use MySqlMemory\Server\MemoryDatabase;
use MySqlMemory\Server\Process;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(Process::class)]
#[Medium]
final class ProcessTest extends TestCase
{
    public function testServeSharesCommittedRowsBetweenConnections(): void
    {
        $database = MemoryDatabase::start('8.4.7', ['shop']);
        $writer = new PDO($database->dsn('shop'), 'root', '');
        $reader = new PDO($database->dsn('shop'), 'root', '');
        $writer->exec('CREATE TABLE items (id INT PRIMARY KEY)');
        $writer->exec('INSERT INTO items VALUES (7)');

        $rows = $reader->query('SELECT id FROM items');
        self::assertNotFalse($rows);
        self::assertSame(7, $rows->fetchColumn());
        $database->stop();
    }
}
