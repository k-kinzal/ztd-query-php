<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Maintenance\PostgreSql as Statement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Utility\MaintenanceCommands;

#[CoversClass(MaintenanceCommands::class)]
#[Medium]
final class MaintenanceCommandsTest extends TestCase
{
    #[TestWith(['VACUUM (INDEX_CLEANUP off, PARALLEL 0, TRUNCATE true) t', 'VACUUM(INDEX_CLEANUP OFF, TRUNCATE TRUE, PARALLEL 0) "public"."t"'])]
    #[TestWith(['ANALYZE VERBOSE t (a)', 'ANALYZE(VERBOSE) "public"."t"("a")'])]
    #[TestWith(['CLUSTER VERBOSE t', 'CLUSTER(VERBOSE) "public"."t"'])]
    #[TestWith(['CLUSTER', 'CLUSTER'])]
    public function testWriteUsesTheCanonicalSpelling(string $sql, string $expected): void
    {
        self::assertSame($expected, MaintenanceCommands::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind($sql))?->toString());
    }

    public function testWriteReturnsNullForOtherOperations(): void
    {
        self::assertNull(MaintenanceCommands::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SHOW ALL')));
    }

    public function testVacuumListsNonDefaultOptions(): void
    {
        $options = new Statement\VacuumOptions(verbose: true, processMain: false, processToast: false, skipDatabaseStats: true, disablePageSkipping: true, skipLocked: true, bufferUsageLimit: new Statement\BufferUsageLimit('0'));
        self::assertSame(['VERBOSE', 'DISABLE_PAGE_SKIPPING', 'SKIP_LOCKED', 'PROCESS_MAIN FALSE', 'PROCESS_TOAST FALSE', 'SKIP_DATABASE_STATS', "BUFFER_USAGE_LIMIT '0'"], array_map(static fn (Tree $tree): string => $tree->toString(), MaintenanceCommands::vacuum($options)));
        self::assertSame(['ONLY_DATABASE_STATS'], array_map(static fn (Tree $tree): string => $tree->toString(), MaintenanceCommands::vacuum(new Statement\VacuumOptions(onlyDatabaseStats: true))));
    }

    public function testAnalyzeListsNonDefaultOptions(): void
    {
        self::assertSame([], MaintenanceCommands::analyze(new Statement\AnalyzeOptions()));
        self::assertCount(2, MaintenanceCommands::analyze(new Statement\AnalyzeOptions(true, true)));
    }

    public function testBufferWritesTheQuantityAsText(): void
    {
        self::assertSame("BUFFER_USAGE_LIMIT '2MB'", MaintenanceCommands::buffer(new Statement\BufferUsageLimit('2MB'))[0]->toString());
        self::assertSame([], MaintenanceCommands::buffer(null));
    }

    public function testOptionsOmitAnEmptyList(): void
    {
        self::assertSame([], MaintenanceCommands::options([]));
    }

    public function testTargetsOmitAnEmptyList(): void
    {
        self::assertSame([], MaintenanceCommands::targets([]));
    }
}
