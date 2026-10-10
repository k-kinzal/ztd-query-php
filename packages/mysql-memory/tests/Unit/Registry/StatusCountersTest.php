<?php

declare(strict_types=1);

namespace Tests\Unit\Registry;

use MySqlMemory\Registry\StatusCounters;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(StatusCounters::class)]
#[Small]
final class StatusCountersTest extends TestCase
{
    public function testAddContributesToTheSessionAndTheServer(): void
    {
        $counters = new StatusCounters();
        $counters->add('Com_select', 1);
        $counters->add('Com_select', 2);
        $counters->add('Com_select', 2);

        self::assertSame([3, 1, 2], [$counters->read('Com_select'), $counters->read('Com_select', 1), $counters->read('Com_select', 2)]);
    }

    public function testReadStartsAtZero(): void
    {
        $counters = new StatusCounters();
        self::assertSame([0, 0], [$counters->read('Questions'), $counters->read('Questions', 1)]);
    }

    public function testAddCountsByteAmountsWithoutChangingOtherConnections(): void
    {
        $counters = new StatusCounters();
        $counters->add('Bytes_sent', 1, 56);
        $counters->add('Bytes_sent', 2, 11);
        $counters->add('Bytes_sent', 1, 0);
        $counters->clear(1);

        self::assertSame([67, 0, 11], [$counters->read('Bytes_sent'), $counters->read('Bytes_sent', 1), $counters->read('Bytes_sent', 2)]);
    }

    public function testClearRetainsGlobalTotalsAndOtherSessions(): void
    {
        $counters = new StatusCounters();
        $counters->add('Questions', 1);
        $counters->add('Questions', 2);
        $counters->clear(1);

        self::assertSame([2, 0, 1], [$counters->read('Questions'), $counters->read('Questions', 1), $counters->read('Questions', 2)]);
    }

    public function testResetDiscardsEveryScopeAndTheFlushTime(): void
    {
        $counters = new StatusCounters();
        $counters->add('Questions', 1);
        $counters->flushedAt = 1.0;
        $counters->reset();

        self::assertSame([0, 0, null], [$counters->read('Questions'), $counters->read('Questions', 1), $counters->flushedAt]);
    }

    public function testClearGlobalRetainsOtherTotalsAndSessionRecords(): void
    {
        $counters = new StatusCounters();
        $counters->add('Aborted_clients');
        $counters->add('Questions', 1);
        $counters->clearGlobal('Aborted_clients');

        self::assertSame([0, 1, 1], [$counters->read('Aborted_clients'), $counters->read('Questions'), $counters->read('Questions', 1)]);
    }

    public function testClearAllRetainsGlobalTotals(): void
    {
        $counters = new StatusCounters();
        $counters->add('Questions', 1);
        $counters->add('Questions', 2);
        $counters->clear();

        self::assertSame([2, 0, 0], [$counters->read('Questions'), $counters->read('Questions', 1), $counters->read('Questions', 2)]);
    }
}
