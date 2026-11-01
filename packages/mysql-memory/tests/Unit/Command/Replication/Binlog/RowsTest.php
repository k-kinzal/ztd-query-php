<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Replication\Binlog;

use MySqlMemory\Command\Replication\Binlog\Format;
use MySqlMemory\Command\Replication\Binlog\Rows;
use MySqlMemory\Command\Replication\Binlog\TableMap;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Rows::class)]
#[Small]
final class RowsTest extends TestCase
{
    public function testApplyConsumesAnInsertImageAndReleasesItsTableMap(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; CREATE TABLE d.t (a INT)');
        $session->binlog->tables[1] = new TableMap(1, 'd', 't', [3], ['']);
        $format = new Format(19, str_repeat("\0", 29) . "\x0a", 0);
        $body = "\x01\0\0\0\0\0\x01\0\x02\0\x01\x01\0\x07\0\0\0";
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        (new Rows())->apply(str_repeat("\0", 19) . $body, 30, $format, $session, $context);
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        self::assertSame([[7]], array_values($table->data->rows));
        self::assertSame([], $session->binlog->tables);
    }

    public function testPartialCountsOnlyJsonColumnsInTheSharedBitmap(): void
    {
        $map = new TableMap(1, 'd', 't', [3, 245, 3, 245], ['', "\x04", '', "\x04"]);
        self::assertSame("\x02", (new Rows())->partial(new \MySqlMemory\Command\Replication\Binlog\Reader("\x01\x02"), $map));
        self::assertSame('', (new Rows())->partial(new \MySqlMemory\Command\Replication\Binlog\Reader("\0"), $map));
    }

    public function testTableResolvesTheRecordedDatabaseBeforeTheTable(): void
    {
        $session = (new Instance())->connect();
        $this->expectExceptionCode(1049);
        (new Rows())->table(new TableMap(1, 'missing', 't', [], []), $session);
    }

    public function testCompatibleRejectsDifferentStorageWidths(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; CREATE TABLE d.t (a VARCHAR(20) CHARACTER SET latin1)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $this->expectExceptionCode(1105);
        (new Rows())->compatible(new TableMap(1, 'd', 't', [15], ["\x50\0"]), $table);
    }

    public function testFindUsesOnlyPresentBeforeImageColumns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; CREATE TABLE d.t (a INT,b INT); INSERT INTO d.t VALUES (1,2),(3,4)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        self::assertSame(2, (new Rows())->find([1 => 4], $table));
        $this->expectExceptionCode(1032);
        (new Rows())->find([1 => 5], $table);
    }

    public function testIndexCandidateDetectsANullableUniqueKeyBeforeImageMismatch(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; CREATE TABLE d.t (a INT UNIQUE,b INT); INSERT INTO d.t VALUES (1,2)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        self::assertTrue((new Rows())->indexCandidate([0 => 1, 1 => 3], $table));
        self::assertFalse((new Rows())->indexCandidate([0 => null, 1 => 3], $table));
    }

    public function testInsertRetainsUniquenessChecks(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; CREATE TABLE d.t (a INT PRIMARY KEY); INSERT INTO d.t VALUES (1)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $this->expectExceptionCode(1062);
        (new Rows())->insert([0 => 1], $table, $session, $context);
    }

    public function testUpdateChangesOnlyTheAfterImageColumns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; CREATE TABLE d.t (a INT PRIMARY KEY,b INT); INSERT INTO d.t VALUES (1,2)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        (new Rows())->update(1, [1 => 7], $table, $session, $context);
        self::assertSame([1, 7], $table->data->rows[1]);
    }
}
