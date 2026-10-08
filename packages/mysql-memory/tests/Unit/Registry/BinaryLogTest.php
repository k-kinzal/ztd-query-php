<?php

declare(strict_types=1);

namespace Tests\Unit\Registry;

use MySqlMemory\Registry\BinaryLog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(BinaryLog::class)]
#[Small]
final class BinaryLogTest extends TestCase
{
    public function testNamePadsTheNumberToSixDigits(): void
    {
        self::assertSame(['binlog.000001', 'binlog.6344632'], [BinaryLog::name(1), BinaryLog::name(6344632)]);
    }

    public function testActiveAnswersTheLastFile(): void
    {
        $log = new BinaryLog();
        $log->rotate();

        self::assertSame('binlog.000002', $log->active());
    }

    public function testEventsAnswersTheEventsEveryFileStartsWith(): void
    {
        self::assertSame([[4, 'Format_desc', 1, 127, 'Server ver: 8.4.7, Binlog ver: 4'], [127, 'Previous_gtids', 1, 158, '']], (new BinaryLog())->events(1, '8.4.7'));
    }

    public function testEventsEndsARotatedFileWithARotateEvent(): void
    {
        $log = new BinaryLog();
        $log->rotate();

        self::assertSame([158, 'Rotate', 1, 202, 'binlog.000002;pos=4'], $log->events(1, '8.4.7')[2]);
    }

    public function testSizeAnswersTheEndOfTheLastEvent(): void
    {
        self::assertSame(158, (new BinaryLog())->size(1, '8.4.7'));
    }

    public function testFindAcceptsTheNameTheIndexListsWithItsDirectory(): void
    {
        $log = new BinaryLog();

        self::assertSame([1, 1, null, null], [$log->find('binlog.000001'), $log->find('./binlog.000001'), $log->find('BINLOG.000001'), $log->find('binlog.000002')]);
    }

    public function testRotateOpensTheNextFile(): void
    {
        $log = new BinaryLog();
        $log->rotate();
        $log->rotate();

        self::assertSame([1, 2, 3], $log->files);
    }

    public function testResetStartsAgainFromANumber(): void
    {
        $log = new BinaryLog();
        $log->rotate();
        $log->reset(6344632);

        self::assertSame('binlog.6344632', $log->active());
    }

    public function testPurgeDeletesTheFilesBeforeOne(): void
    {
        $log = new BinaryLog();
        $log->rotate();
        $log->rotate();
        $log->purge(2);

        self::assertSame([2, 3], $log->files);
    }

    public function testDescribedEndsTheFormatDescriptionAByteEarlierBeforeMySql83(): void
    {
        self::assertSame([126, 126, 127, 127], [BinaryLog::described('8.0.44'), BinaryLog::described('8.2.0'), BinaryLog::described('8.4.7'), BinaryLog::described('9.1.0')]);
        self::assertSame(157, (new BinaryLog())->size(1, '8.0.44'));
    }
}
