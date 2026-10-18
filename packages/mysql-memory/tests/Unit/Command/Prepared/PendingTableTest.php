<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Prepared;

use MySqlMemory\Command\Prepared\PendingTable;
use MySqlMemory\Instance;
use MySqlMemory\Session\Access\Preparation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(PendingTable::class)]
#[Medium]
final class PendingTableTest extends TestCase
{
    public function testRunReusesTheSameHeaderAcrossADatabaseTransition(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET sql_mode=''");
        $definition = (new Preparation())->read('CREATE TABLE later.t(a INT) ENGINE=missing PARTITION BY HASH(ROW(1,2))', $session);
        self::assertInstanceOf(PendingTable::class, $definition);
        self::assertSame([['Warning', 1286, "Unknown storage engine 'missing'"], ['Warning', 1266, "Using storage engine InnoDB for table 't'"]], $session->diagnostics->conditions);
        $header = $definition->header;
        $before = $definition->run($session, 'request label, not SQL', []);
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $before[0]);
        self::assertSame(1049, $before[0]->getCode());
        $session->query('CREATE DATABASE later');
        $after = $definition->run($session, 'another request label', []);

        self::assertSame($header, $definition->header);
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $after[0]);
        self::assertSame(1564, $after[0]->getCode());
        self::assertSame([['Error', 1564, 'This partition function is not allowed']], $session->diagnostics->conditions);
    }

    public function testExecutePreservesThePreparationDatabase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');
        $definition = (new Preparation())->read('CREATE TABLE t(a INT) PARTITION BY HASH(ROW(1,2))', $session);
        self::assertInstanceOf(PendingTable::class, $definition);
        $session->query('DROP DATABASE d');
        $this->expectExceptionCode(1049);
        $this->expectExceptionMessage("Unknown database 'd'");

        $definition->execute($session, 'opaque label', []);
    }
}
