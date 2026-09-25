<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Html;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Catalog\AnalysisProblem;
use SqlCatalog\Catalog\CallSite;
use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Catalog\Finding;
use SqlCatalog\Catalog\FindingRule;
use SqlCatalog\Catalog\Severity;
use SqlCatalog\Reporter\Html\CatalogIndex;
use SqlCatalog\Reporter\Html\Scope;
use SqlCatalog\Sql\StatementKind;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\TextPattern;

#[CoversClass(CatalogIndex::class)]
#[UsesClass(AnalysisProblem::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(Finding::class)]
#[UsesClass(FindingRule::class)]
#[UsesClass(Severity::class)]
#[UsesClass(Scope::class)]
#[UsesClass(StatementKind::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextPattern::class)]
final class CatalogIndexTest extends TestCase
{
    public function testEntriesAreInReportingOrder(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('b', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('b.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
        ]);

        self::assertSame(['a', 'b'], array_column((new CatalogIndex($catalog))->entries(), 'id'));
    }

    public function testByTableListsTheMostNamedTableFirst(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['users'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('b', StatementKind::Select, TextPattern::fromText('SELECT 2'), ['posts', 'users'], [], new CallSite('a.php', 2, 'f', 'pdo.query'), []),
            new CatalogEntry('c', StatementKind::Select, TextPattern::fromText('SELECT 3'), ['posts'], [], new CallSite('a.php', 3, 'f', 'pdo.query'), []),
            new CatalogEntry('d', StatementKind::Select, TextPattern::fromText('SELECT 4'), ['users'], [], new CallSite('a.php', 4, 'f', 'pdo.query'), []),
        ]);
        $byTable = (new CatalogIndex($catalog))->byTable();

        self::assertSame(['users', 'posts'], array_keys($byTable));
        self::assertSame(['a', 'b', 'd'], array_column($byTable['users'], 'id'));
    }

    public function testByFunctionKeepsTheOrderFunctionsAreMet(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 5, 'g', 'pdo.query'), []),
            new CatalogEntry('b', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
        ]);

        self::assertSame(['f', 'g'], array_keys((new CatalogIndex($catalog))->byFunction()));
    }

    public function testByClassGroupsMethodsUnderTheirClass(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'App\\Users::find', 'pdo.query'), []),
            new CatalogEntry('b', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('a.php', 2, 'App\\Users::all', 'pdo.query'), []),
            new CatalogEntry('c', StatementKind::Select, TextPattern::fromText('SELECT 3'), [], [], new CallSite('a.php', 3, 'helper', 'pdo.query'), []),
        ]);
        $byClass = (new CatalogIndex($catalog))->byClass();

        self::assertSame(['App\\Users'], array_keys($byClass));
        self::assertCount(2, $byClass['App\\Users']);
    }

    public function testByNamespaceListsTheGlobalNamespaceFirst(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'App\\Users::find', 'pdo.query'), []),
            new CatalogEntry('b', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('a.php', 2, 'helper', 'pdo.query'), []),
        ]);

        self::assertSame(['', 'App'], array_keys((new CatalogIndex($catalog))->byNamespace()));
    }

    public function testByFileIsInPathOrder(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('src/b.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('b', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('src/a.php', 1, 'f', 'pdo.query'), []),
        ]);

        self::assertSame(['src/a.php', 'src/b.php'], array_keys((new CatalogIndex($catalog))->byFile()));
    }

    public function testByDirectoryGroupsFilesUnderTheirDirectory(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('src/a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('b', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('index.php', 1, 'f', 'pdo.query'), []),
        ]);

        self::assertSame(['' => ['index.php'], 'src' => ['src/a.php']], (new CatalogIndex($catalog))->byDirectory());
    }

    public function testByFileIncludesUnreadFilesOnlyWhenTheirSourceCanBeShown(): void
    {
        $catalog = new Catalog([], [
            new AnalysisProblem('src/broken.php', 'Syntax error'),
            new AnalysisProblem('missing.php', 'No snapshot'),
            new AnalysisProblem('empty.php', 'Empty snapshot'),
        ], ['src/broken.php' => '<?php function {', 'empty.php' => '']);
        $index = new CatalogIndex($catalog);

        self::assertSame(['empty.php' => [], 'src/broken.php' => []], $index->byFile());
        self::assertSame(['' => ['empty.php'], 'src' => ['src/broken.php']], $index->byDirectory());
    }

    public function testByRuleListsTheMostReportedRuleFirst(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), [
                Finding::of(FindingRule::DynamicSql, 'one'),
                Finding::of(FindingRule::ExternalInput, 'two'),
            ]),
            new CatalogEntry('b', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('a.php', 2, 'f', 'pdo.query'), [
                Finding::of(FindingRule::DynamicSql, 'three'),
            ]),
        ]);

        self::assertSame(['dynamic-sql', 'external-input'], array_keys((new CatalogIndex($catalog))->byRule()));
    }

    public function testUsageCountsReadsWritesSchemaAndAttention(): void
    {
        $entries = [
            new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('b', StatementKind::Insert, TextPattern::fromText('INSERT'), [], [], new CallSite('a.php', 2, 'f', 'pdo.query'), [Finding::of(FindingRule::DynamicSql, 'x')]),
            new CatalogEntry('c', StatementKind::Alter, TextPattern::fromText('ALTER'), [], [], new CallSite('a.php', 3, 'f', 'pdo.query'), [Finding::of(FindingRule::AnalysisIncomplete, 'x')]),
            new CatalogEntry('d', StatementKind::Show, TextPattern::fromText('SHOW'), [], [], new CallSite('a.php', 4, 'f', 'pdo.query'), []),
        ];

        self::assertSame(
            ['reads' => 1, 'writes' => 1, 'schema' => 1, 'other' => 1, 'attention' => 1],
            (new CatalogIndex(new Catalog()))->usage($entries),
        );
    }

    public function testUsageOfCountsTruncateAsASchemaChange(): void
    {
        $entry = new CatalogEntry('a', StatementKind::Truncate, TextPattern::fromText('TRUNCATE t'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []);

        self::assertSame('schema', (new CatalogIndex(new Catalog()))->usageOf($entry));
    }

    public function testTablesOfCountsMostNamedFirst(): void
    {
        $entries = [
            new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['users', 'posts'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('b', StatementKind::Select, TextPattern::fromText('SELECT 2'), ['posts'], [], new CallSite('a.php', 2, 'f', 'pdo.query'), []),
        ];

        self::assertSame(['posts' => 2, 'users' => 1], (new CatalogIndex(new Catalog()))->tablesOf($entries));
    }

    public function testFunctionsOfCountsMostFirst(): void
    {
        $entries = [
            new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('b', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('a.php', 2, 'g', 'pdo.query'), []),
            new CatalogEntry('c', StatementKind::Select, TextPattern::fromText('SELECT 3'), [], [], new CallSite('a.php', 3, 'g', 'pdo.query'), []),
        ];

        self::assertSame(['g' => 2, 'f' => 1], (new CatalogIndex(new Catalog()))->functionsOf($entries));
    }

    public function testAlongsideCountsTheTablesNamedWithOne(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['users', 'posts'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('b', StatementKind::Select, TextPattern::fromText('SELECT 2'), ['users', 'meta'], [], new CallSite('a.php', 2, 'f', 'pdo.query'), []),
            new CatalogEntry('c', StatementKind::Select, TextPattern::fromText('SELECT 3'), ['users', 'meta'], [], new CallSite('a.php', 3, 'f', 'pdo.query'), []),
        ]);

        self::assertSame(['meta' => 2, 'posts' => 1], (new CatalogIndex($catalog))->alongside('users'));
    }

    public function testHotspotsRankFunctionsByWhatWasReported(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), [Finding::of(FindingRule::DynamicSql, 'x')]),
            new CatalogEntry('b', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('a.php', 2, 'g', 'pdo.query'), [Finding::of(FindingRule::ExternalInput, 'x')]),
            new CatalogEntry('c', StatementKind::Select, TextPattern::fromText('SELECT 3'), [], [], new CallSite('a.php', 3, 'h', 'pdo.query'), [Finding::of(FindingRule::AnalysisIncomplete, 'x')]),
        ]);

        self::assertSame(
            [
                ['function' => 'g', 'file' => 'a.php', 'high' => 1, 'medium' => 0],
                ['function' => 'f', 'file' => 'a.php', 'high' => 0, 'medium' => 1],
            ],
            (new CatalogIndex($catalog))->hotspots(),
        );
    }

    public function testMostFirstBreaksTiesByName(): void
    {
        self::assertSame(['b' => 2, 'a' => 1, 'c' => 1], (new CatalogIndex(new Catalog()))->mostFirst(['c' => 1, 'a' => 1, 'b' => 2]));
    }

    public function testUsageOfTellsAReadFromAnotherStatementThatChangesNoRow(): void
    {
        $site = new CallSite('a.php', 1, 'f', 'pdo.query');
        $index = new CatalogIndex(new Catalog());

        self::assertSame('reads', $index->usageOf(new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], $site, [])));
        self::assertSame('other', $index->usageOf(new CatalogEntry('b', StatementKind::Show, TextPattern::fromText('SHOW TABLES'), [], [], $site, [])));
        self::assertSame('writes', $index->usageOf(new CatalogEntry('c', StatementKind::Insert, TextPattern::fromText('INSERT'), [], [], $site, [])));
    }

    public function testByClassAndByNamespaceOrderNamesWithoutRegardToCase(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'B\\B::f', 'pdo.query'), []),
            new CatalogEntry('b', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('a.php', 2, 'a\\a::f', 'pdo.query'), []),
        ]);
        $index = new CatalogIndex($catalog);

        self::assertSame(['a\\a', 'B\\B'], array_keys($index->byClass()));
        self::assertSame(['a', 'B'], array_keys($index->byNamespace()));
    }

    public function testByTableBreaksTiesByName(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['zeta'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('b', StatementKind::Select, TextPattern::fromText('SELECT 2'), ['alpha'], [], new CallSite('a.php', 2, 'f', 'pdo.query'), []),
        ]);

        self::assertSame(['alpha', 'zeta'], array_keys((new CatalogIndex($catalog))->byTable()));
    }

    public function testHotspotsKeepCountingPastAStatementNothingWasReportedOn(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('b', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('a.php', 2, 'g', 'pdo.query'), [Finding::of(FindingRule::DynamicSql, 'x')]),
            new CatalogEntry('c', StatementKind::Select, TextPattern::fromText('SELECT 3'), [], [], new CallSite('a.php', 3, 'g', 'pdo.query'), [Finding::of(FindingRule::DynamicSql, 'x')]),
            new CatalogEntry('d', StatementKind::Select, TextPattern::fromText('SELECT 4'), [], [], new CallSite('a.php', 4, 'h', 'pdo.query'), [Finding::of(FindingRule::DynamicSql, 'x')]),
            new CatalogEntry('e', StatementKind::Select, TextPattern::fromText('SELECT 5'), [], [], new CallSite('a.php', 5, 'h', 'pdo.query'), [Finding::of(FindingRule::ExternalInput, 'x')]),
        ]);

        self::assertSame(
            [
                ['function' => 'h', 'file' => 'a.php', 'high' => 1, 'medium' => 1],
                ['function' => 'g', 'file' => 'a.php', 'high' => 0, 'medium' => 2],
            ],
            (new CatalogIndex($catalog))->hotspots(),
        );
    }
}
