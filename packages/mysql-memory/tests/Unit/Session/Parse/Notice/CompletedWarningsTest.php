<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Notice;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Notice\CompletedWarnings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(CompletedWarnings::class)]
#[Small]
final class CompletedWarningsTest extends TestCase
{
    public function testBeforeKeepsCompletedOccurrencesAndRespectsTheInputBoundary(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $sql = 'CHANGE MASTER TO MASTER_HEARTBEAT_PERIOD=0.0001, MASTER_HEARTBEAT_PERIOD=0, INVALID';
        $warnings = new CompletedWarnings();

        self::assertSame([], $warnings->before($sql, 0, $session));
        self::assertSame([1703], array_values(array_map(static fn ($notice): int => $notice->code(), $warnings->before($sql, strlen($sql), $session))));
    }
    public function testBeforeKeepsEachQueryCacheNoticeBeforeTheLaterRefusal(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $sql = 'FLUSH QUERY CACHE, QUERY CACHE, ?';

        self::assertSame([1681, 1681], array_values(array_map(static fn ($notice): int => $notice->code(), (new CompletedWarnings())->before($sql, strlen($sql), $session))));
    }

}
