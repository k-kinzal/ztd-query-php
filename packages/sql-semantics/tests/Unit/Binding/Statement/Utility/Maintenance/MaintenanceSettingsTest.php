<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Utility\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
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
}
