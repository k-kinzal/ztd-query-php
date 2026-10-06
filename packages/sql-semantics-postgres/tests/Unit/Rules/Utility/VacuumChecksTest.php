<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Rules\Utility\VacuumChecks;
use SqlSemantics\Statement\Fact\Diagnostic;

#[CoversClass(VacuumChecks::class)]
#[Medium]
final class VacuumChecksTest extends TestCase
{
    public function testCheckReportsWhatFullExcludes(): void
    {
        self::assertSame(
            ['BUFFER_USAGE_LIMIT cannot be specified for VACUUM FULL', 'VACUUM FULL cannot be performed in parallel', 'VACUUM option DISABLE_PAGE_SKIPPING cannot be used with FULL', 'PROCESS_TOAST required with VACUUM FULL'],
            array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Semantics(Dialect::PostgreSql))->analyze("VACUUM (FULL, BUFFER_USAGE_LIMIT '1MB', PARALLEL 2, DISABLE_PAGE_SKIPPING, PROCESS_TOAST off) t")->facts->diagnostics),
        );
    }

    public function testCheckAcceptsFullWithAnalyzeAndNoParallelWorkers(): void
    {
        self::assertSame([], (new Semantics(Dialect::PostgreSql))->analyze("VACUUM (FULL, ANALYZE, BUFFER_USAGE_LIMIT '1MB', PARALLEL 0, DISABLE_PAGE_SKIPPING false) t (a)")->facts->diagnostics);
    }

    public function testCheckReportsColumnsWithoutAnalyze(): void
    {
        self::assertSame(
            ['ANALYZE option must be specified when a column list is provided'],
            array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Semantics(Dialect::PostgreSql))->analyze('VACUUM (ANALYZE off) t (a)')->facts->diagnostics),
        );
    }

    public function testCheckReportsAnIndexCleanupThatIsNeitherAutoNorBoolean(): void
    {
        self::assertSame(
            ['index_cleanup requires a Boolean value'],
            array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Semantics(Dialect::PostgreSql))->analyze("VACUUM (INDEX_CLEANUP 'AUTO', INDEX_CLEANUP, INDEX_CLEANUP on, INDEX_CLEANUP sometimes)")->facts->diagnostics),
        );
    }

    public function testCheckReportsDatabaseStatsWithTables(): void
    {
        self::assertSame(
            ['ONLY_DATABASE_STATS cannot be specified with a list of tables'],
            array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Semantics(Dialect::PostgreSql))->analyze('VACUUM (ONLY_DATABASE_STATS, FREEZE) t')->facts->diagnostics),
        );
    }

    public function testWorkersReportsEachInvalidValue(): void
    {
        self::assertSame(
            ['parallel option requires a value between 0 and 1024', 'parallel requires an integer value', 'parallel workers for vacuum must be between 0 and 1024', 'parallel workers for vacuum must be between 0 and 1024'],
            array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Semantics(Dialect::PostgreSql))->analyze("VACUUM (PARALLEL, PARALLEL '2', PARALLEL 1025, PARALLEL -1, PARALLEL 1024)")->facts->diagnostics),
        );
    }

    public function testCombinedReportsAnOptionDatabaseStatsExcludes(): void
    {
        self::assertSame(
            ['ONLY_DATABASE_STATS cannot be specified with other VACUUM options'],
            array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Semantics(Dialect::PostgreSql))->analyze('VACUUM (ONLY_DATABASE_STATS, SKIP_LOCKED)')->facts->diagnostics),
        );
    }

    public function testCombinedAcceptsTheOptionsDatabaseStatsAllows(): void
    {
        self::assertSame([], (new Semantics(Dialect::PostgreSql))->analyze('VACUUM (ONLY_DATABASE_STATS, VERBOSE, PROCESS_MAIN, PROCESS_TOAST, INDEX_CLEANUP off, TRUNCATE, PARALLEL 2, FREEZE false)')->facts->diagnostics);
    }
}
