<?php

declare(strict_types=1);

namespace Tests\Unit\Iterator\Source;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Instance;
use MySqlMemory\Iterator\Source\TableScanIterator;
use MySqlMemory\Plan\Path\Source\TableScan;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(TableScanIterator::class)]
#[Small]
final class TableScanIteratorTest extends TestCase
{
    public function testReadAnswersTheRowsInPrimaryKeyOrder(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY, v CHAR(1))');
        $session->query("INSERT INTO t VALUES (3, 'c'), (1, 'a'), (2, 'b')");
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $iterator = new TableScanIterator(new TableScan($table));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([1, 'a'], $iterator->read());
        self::assertSame([2, 'b'], $iterator->read());
        self::assertSame([3, 'c'], $iterator->read());
        self::assertNull($iterator->read());
    }

    public function testReadAnswersTheRowsOfATableWithoutKeysInInsertionOrder(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (v VARCHAR(3), n DOUBLE, x DECIMAL(5,2))');
        $session->query("INSERT INTO t VALUES ('q', 1.5, 2.25), ('p', NULL, NULL)");
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $iterator = new TableScanIterator(new TableScan($table));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame(['q', 1.5, '2.25'], $iterator->read());
        self::assertSame(['p', null, null], $iterator->read());
        self::assertNull($iterator->read());
    }

    public function testReadRecordsTheNumberOfTheRowReadLast(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY)');
        $session->query('INSERT INTO t VALUES (2), (1)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $iterator = new TableScanIterator(new TableScan($table));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));
        $before = $iterator->current;
        $iterator->read();
        $first = $iterator->current;
        $iterator->read();
        $second = $iterator->current;
        $iterator->read();

        self::assertSame([null, 2, 1, null], [$before, $first, $second, $iterator->current]);
    }

    public function testInitTakesTheRowsAsTheyAreWhenTheScanStarts(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY)');
        $session->query('INSERT INTO t VALUES (1)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $iterator = new TableScanIterator(new TableScan($table));
        $iterator->init($frame);
        $session->query('INSERT INTO t VALUES (0)');
        $first = [$iterator->read(), $iterator->read()];
        $iterator->init($frame);

        self::assertSame([[1], null], $first);
        self::assertSame([0], $iterator->read());
        self::assertSame([1], $iterator->read());
    }
}
