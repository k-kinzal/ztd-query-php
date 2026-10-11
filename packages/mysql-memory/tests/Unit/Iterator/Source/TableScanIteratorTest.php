<?php

declare(strict_types=1);

namespace Tests\Unit\Iterator\Source;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Instance;
use MySqlMemory\Iterator\Source\TableScanIterator;
use MySqlMemory\Plan\Path\Source\TableScan;
use MySqlMemory\Result\ResultSet;
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

    public function testInitShowsTimestampsInTheZoneOfTheSession(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (ts TIMESTAMP)');
        $session->query("INSERT INTO t VALUES ('2024-01-01 00:00:00')");
        $session->query("SET time_zone = '-05:00'");

        $reply = $session->query('SELECT ts FROM t')[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([['2023-12-31 19:00:00']], $reply->rows);
    }
    public function testInitReadsTheRowsTheTransactionOfTheSessionSees(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $reader = $instance->connect('root', 'localhost', 'd');
        $writer = $instance->connect('root', 'localhost', 'd');
        $reader->query('CREATE TABLE t (a INT); INSERT INTO t VALUES (1)');
        $writer->query('BEGIN; INSERT INTO t VALUES (2)');
        $table = $instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $iterator = new TableScanIterator(new TableScan($table));
        $iterator->init(new Frame(new Context($reader->modes(), $reader->diagnostics, $reader->variables, 0.0)));
        $first = [$iterator->read(), $iterator->read()];
        $iterator->locking = \MySqlMemory\Concurrency\LockMode::Shared;
        $iterator->init(new Frame(new Context($reader->modes(), $reader->diagnostics, $reader->variables, 0.0)));

        self::assertSame([[[1], null], [1], [2]], [$first, $iterator->read(), $iterator->read()]);
    }

    public function testMatchesRetainsUnsignedPrimaryKeyPrecision(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (id BIGINT UNSIGNED PRIMARY KEY); INSERT INTO t VALUES (18446744073709551614),(18446744073709551615)');
        $result = $session->query('SELECT id FROM t WHERE id=18446744073709551615')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['18446744073709551615']], $result->rows);
    }

    public function testReadRetainsTheMatchedRowNumberAndEndsAfterOneKeyCandidate(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY); INSERT INTO t VALUES (2),(1),(3)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $scan = new TableScan($table);
        $domain = \MySqlMemory\Typing\Domain::integer();
        $lookup = new \MySqlMemory\Plan\Path\Source\KeyLookup($scan, [[0, new \MySqlMemory\Evaluation\Leaf\Constant($domain, 2), \MySqlMemory\Evaluation\Operator\Comparison\Comparator::of($domain, $domain, '=', $session->settings()->connectionCollation)]]);
        $builder = new \MySqlMemory\Iterator\Builder();
        $iterator = $builder->build($lookup);
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));
        $row = $iterator->read();
        $number = $builder->scans[spl_object_id($scan)]->current;

        self::assertSame([2], $row);
        self::assertSame(1, $number);
        self::assertNull($iterator->read());
        self::assertNull($builder->scans[spl_object_id($scan)]->current);
    }
}
