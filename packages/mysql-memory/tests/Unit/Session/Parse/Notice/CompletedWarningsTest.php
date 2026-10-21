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
    public function testBeforeKeepsUnfinishedDotReferencesWithoutDuplicatingACompleteTree(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $warnings = new CompletedWarnings();
        $sql = 'SELECT 1 FROM .t,';

        self::assertSame([1287], array_values(array_map(static fn ($notice): int => $notice->code(), $warnings->before($sql, strlen($sql), $session))));
        self::assertSame([], $warnings->before($sql, strlen($sql), $session, true));
    }

    public function testBeforeKeepsAlterIgnoreOnlyAfterTheTargetIsComplete(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $warnings = new CompletedWarnings();
        $sql = 'ALTER IGNORE TABLE t ALTER a SET DEFAULT (1)';

        self::assertSame([], $warnings->before('ALTER IGNORE TABLE', 18, $session));
        self::assertSame([1681], array_values(array_map(static fn ($notice): int => $notice->code(), $warnings->before($sql, strlen($sql), $session))));
    }

    public function testBeforeKeepsGroupingDirectionsAheadOfMalformedInput(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $sql = 'SELECT 1 FROM DUAL GROUP BY 1 ASC,1 DESC BROKEN';

        self::assertSame([1287, 1287], array_values(array_map(static fn ($notice): int => $notice->code(), (new CompletedWarnings())->before($sql, strlen($sql), $session))));
    }

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

    public function testBeforeKeepsAnExplainModifierAfterTheGrammarSelectsItsOptionRole(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $warnings = new CompletedWarnings();
        $sql = 'EXPLAIN EXTENDED FOR CONNECTION ?';
        self::assertSame([], $warnings->before('EXPLAIN EXTENDED', 16, $session));
        self::assertSame([1681], array_values(array_map(static fn ($notice): int => $notice->code(), $warnings->before($sql, strlen($sql), $session))));
    }

}
