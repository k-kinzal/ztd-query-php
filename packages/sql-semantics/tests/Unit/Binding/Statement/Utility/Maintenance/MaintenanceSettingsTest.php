<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Utility\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Utility\Maintenance\MaintenanceSettings;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Maintenance\PostgreSql\IndexCleanup;
use SqlSemantics\Model\Statement\Maintenance\PostgreSql\VacuumStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(MaintenanceSettings::class)]
#[Medium]
final class MaintenanceSettingsTest extends TestCase
{
    public function testVacuumReadsEveryOptionDomain(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('VACUUM (DISABLE_PAGE_SKIPPING, SKIP_LOCKED true, INDEX_CLEANUP, PROCESS_MAIN off, PROCESS_TOAST 0, TRUNCATE on, PARALLEL 4, SKIP_DATABASE_STATS, BUFFER_USAGE_LIMIT 256)');
        self::assertInstanceOf(VacuumStatement::class, $statement);
        $options = $statement->options;
        self::assertTrue($options->disablePageSkipping);
        self::assertTrue($options->skipLocked);
        self::assertSame(IndexCleanup::Auto, $options->indexCleanup);
        self::assertFalse($options->processMain);
        self::assertFalse($options->processToast);
        self::assertTrue($options->truncate);
        self::assertSame(4, $options->parallel);
        self::assertTrue($options->skipDatabaseStats);
        self::assertSame(256, $options->bufferUsageLimit?->kilobytes);
    }

    public function testAnalyzeReadsTheLegacyVerboseKeyword(): void
    {
        $source = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('ANALYZE VERBOSE'), ['AnalyzeStmt'])[0];
        self::assertTrue(MaintenanceSettings::analyze($source, (new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql)))->verbose);
    }

    public function testLegacyReadsOnlyWrittenKeywords(): void
    {
        $source = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('VACUUM FREEZE ANALYZE'), ['VacuumStmt'])[0];
        self::assertSame(['freeze' => true, 'analyze' => true], MaintenanceSettings::legacy($source));
    }

    public function testPresentIgnoresEmptyOptionalRules(): void
    {
        $source = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('VACUUM FULL'), ['VacuumStmt'])[0];
        self::assertTrue(MaintenanceSettings::present($source, 'opt_full'));
        self::assertFalse(MaintenanceSettings::present($source, 'opt_verbose'));
    }

    public function testVacuumReadsIndexCleanupWords(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $on = $binder->bind('VACUUM (INDEX_CLEANUP true)');
        $auto = $binder->bind("VACUUM (INDEX_CLEANUP 'AUTO')");
        self::assertInstanceOf(VacuumStatement::class, $on);
        self::assertInstanceOf(VacuumStatement::class, $auto);
        self::assertSame(IndexCleanup::On, $on->options->indexCleanup);
        self::assertSame(IndexCleanup::Auto, $auto->options->indexCleanup);
    }

    public function testBuildAppliesServerDefaults(): void
    {
        $options = MaintenanceSettings::build(['full' => true], null, null, null, new \SqlParser\Parser\Node('VacuumStmt', 0, []));
        self::assertTrue($options->full);
        self::assertTrue($options->processToast);
        self::assertNull($options->truncate);
        $this->expectException(\SqlSemantics\InvalidSql::class);
        MaintenanceSettings::build(['full' => true], null, 2, null, new \SqlParser\Parser\Node('VacuumStmt', 0, []));
    }

    #[TestWith(['VACUUM t', 'VACUUM "public"."t"'])]
    #[TestWith(['VACUUM (VERBOSE false) t', 'VACUUM "public"."t"'])]
    #[TestWith(['VACUUM (VERBOSE, ANALYZE, FREEZE, FULL, SKIP_DATABASE_STATS) t', 'VACUUM(FULL, FREEZE, VERBOSE, ANALYZE, SKIP_DATABASE_STATS) "public"."t"'])]
    #[TestWith(['VACUUM (ONLY_DATABASE_STATS)', 'VACUUM(ONLY_DATABASE_STATS)'])]
    #[TestWith(['VACUUM (BUFFER_USAGE_LIMIT 128) t', 'VACUUM(BUFFER_USAGE_LIMIT \'128\') "public"."t"'])]
    #[TestWith(['VACUUM (PROCESS_MAIN false, PROCESS_TOAST false) t', 'VACUUM(PROCESS_MAIN FALSE, PROCESS_TOAST FALSE) "public"."t"'])]
    #[TestWith(['VACUUM (FULL false, PARALLEL 2) t', 'VACUUM(PARALLEL 2) "public"."t"'])]
    #[TestWith(['VACUUM (INDEX_CLEANUP off) t', 'VACUUM(INDEX_CLEANUP OFF) "public"."t"'])]
    #[TestWith(['VACUUM FULL FREEZE VERBOSE ANALYZE t', 'VACUUM(FULL, FREEZE, VERBOSE, ANALYZE) "public"."t"'])]
    #[TestWith(['ANALYZE t', 'ANALYZE "public"."t"'])]
    #[TestWith(['ANALYZE (VERBOSE false, SKIP_LOCKED false) t', 'ANALYZE "public"."t"'])]
    #[TestWith(['ANALYZE (VERBOSE, SKIP_LOCKED, BUFFER_USAGE_LIMIT 256) t', 'ANALYZE(VERBOSE, SKIP_LOCKED, BUFFER_USAGE_LIMIT \'256\') "public"."t"'])]
    public function testVacuumAndAnalyzeSpellOnlyTheNonDefaultOptions(string $sql, string $expected): void
    {
        self::assertSame($expected, (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind($sql)->toString());
    }

    #[TestWith(['VACUUM (BOGUS) t'])]
    #[TestWith(['ANALYZE (FULL) t'])]
    #[TestWith(['VACUUM (FULL, PARALLEL 2) t'])]
    public function testVacuumAndAnalyzeRejectUnknownOrIncompatibleOptions(string $sql): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::MaintenanceOption->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind($sql);
    }
}
