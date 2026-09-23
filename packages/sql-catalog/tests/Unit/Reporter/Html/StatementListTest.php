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
use SqlCatalog\Catalog\Placeholder;
use SqlCatalog\Catalog\Resolution;
use SqlCatalog\Catalog\Severity;
use SqlCatalog\Catalog\StatementPart;
use SqlCatalog\Catalog\ValueDomain;
use SqlCatalog\Reporter\Html\CatalogIndex;
use SqlCatalog\Reporter\Html\CatalogStatistics;
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\Palette;
use SqlCatalog\Reporter\Html\ReportSite;
use SqlCatalog\Reporter\Html\Scope;
use SqlCatalog\Reporter\Html\SqlFormatter;
use SqlCatalog\Reporter\Html\SqlHighlighter;
use SqlCatalog\Reporter\Html\StatementList;
use SqlCatalog\Reporter\Html\StatementRow;
use SqlCatalog\Reporter\Html\TableName;
use SqlCatalog\Sql\StatementKind;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\Origin;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(StatementList::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(CatalogIndex::class)]
#[UsesClass(CatalogStatistics::class)]
#[UsesClass(Finding::class)]
#[UsesClass(FindingRule::class)]
#[UsesClass(HtmlText::class)]
#[UsesClass(Palette::class)]
#[UsesClass(ReportSite::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Scope::class)]
#[UsesClass(Severity::class)]
#[UsesClass(SqlHighlighter::class)]
#[UsesClass(StatementKind::class)]
#[UsesClass(StatementPart::class)]
#[UsesClass(StatementRow::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(AnalysisProblem::class)]
#[UsesClass(Placeholder::class)]
#[UsesClass(ValueDomain::class)]
#[UsesClass(SqlFormatter::class)]
#[UsesClass(TableName::class)]
#[UsesClass(Origin::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TypeShape::class)]
final class StatementListTest extends TestCase
{
    public function testRowsListEveryStatementAndSaySoWhenThereIsNone(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 4, 'f', 'pdo.query'), []);
        $site = new ReportSite(new Catalog([$entry]));
        $list = new StatementList();

        self::assertStringStartsWith('<ol class="rows"><li class="row" data-kind="select"', $list->rows($site, 'index.html', [$entry]));
        self::assertSame('<p class="none">No statement here.</p>', $list->rows($site, 'index.html', []));
    }

    public function testFacetsOfferOnlyWhatWouldNarrowTheListing(): void
    {
        $entries = [
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('a2', StatementKind::Insert, TextPattern::fromText('INSERT'), [], [], new CallSite('a.php', 2, 'f', 'pdo.query'), [Finding::of(FindingRule::DynamicSql, 'x')]),
        ];

        self::assertSame(
            '<div class="facets"><input type="search" class="facet-search" placeholder="Narrow by text…" autocomplete="off" spellcheck="false">'
            . '<span class="facet-group"><button type="button" class="chip facet k-select" data-facet="kind" data-value="select">SELECT<span class="facet-count">1</span></button>'
            . '<button type="button" class="chip facet k-insert" data-facet="kind" data-value="insert">INSERT<span class="facet-count">1</span></button></span>'
            . '<span class="facet-shown" data-total="2"></span><button type="button" class="facet-clear" hidden>Clear</button></div>',
            (new StatementList())->facets($entries),
        );
    }

    public function testGroupIsSilentWhenEveryRowSharesTheValue(): void
    {
        self::assertSame('', (new StatementList())->group('kind', ['select' => 3], static fn (string $value): string => 'k-' . $value));
    }

    public function testFacetsIsWrittenExactly(): void
    {
        $entries = [
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromSegments([
                new LiteralText('SELECT id FROM posts WHERE slug = '),
                new TextHole(Origin::External, TypeShape::unknown(), '$_GET["s"]'),
            ]), ['posts'], [new Placeholder('?', 0, null, new ValueDomain('int', [7], true, []))], new CallSite('src/a.php', 4, 'App\\R::find', 'pdo.query'), [
                Finding::of(FindingRule::ExternalInput, 'spliced'),
            ], false, ['App\\R::run', 'App\\R::find']),
            new CatalogEntry('a2', StatementKind::Insert, TextPattern::fromText('INSERT INTO posts (id) VALUES (1)'), ['posts'], [], new CallSite('src/a.php', 9, 'App\\R::add', 'pdo.query'), []),
            new CatalogEntry('a3', StatementKind::Update, TextPattern::fromText('UPDATE posts SET title = ? WHERE id = ?'), ['posts'], [], new CallSite('src/a.php', 14, 'App\\R::add', 'pdo.prepare'), [
                Finding::of(FindingRule::PlaceholderCountMismatch, 'one bound'),
            ]),
            new CatalogEntry('b1', StatementKind::Delete, TextPattern::fromText('DELETE FROM users WHERE id = 1'), ['users'], [], new CallSite('src/b.php', 3, 'App\\Admin\\U::drop', 'pdo.query'), []),
            new CatalogEntry('b2', StatementKind::Alter, TextPattern::fromText('ALTER TABLE users ADD x INT'), ['users'], [], new CallSite('src/b.php', 8, 'App\\Admin\\U::migrate', 'pdo.exec'), []),
            new CatalogEntry('c1', StatementKind::Select, TextPattern::fromHole(new TextHole(Origin::Budget, TypeShape::unknown())), [], [], new CallSite('lib/c.php', 2, 'helper', 'mysqli.query'), [
                Finding::of(FindingRule::AnalysisIncomplete, 'stopped'),
            ], true, [], true),
            new CatalogEntry('c2', StatementKind::Unknown, TextPattern::fromHole(new TextHole(Origin::Unreached, TypeShape::unknown(), '$db->query($sql)')), [], [], new CallSite('lib/c.php', 6, '{main}', 'unmatched'), [
                Finding::of(FindingRule::CallNotAnalyzed, 'unseen'),
            ]),
            new CatalogEntry('d1', StatementKind::Select, TextPattern::fromText('SELECT * FROM posts p JOIN users u ON u.id = p.author'), ['posts', 'users'], [], new CallSite('src/d.php', 1, 'App\\R::find', 'pdo.query'), [
                Finding::of(FindingRule::DynamicSql, 'dyn'),
            ]),
            new CatalogEntry('e1', StatementKind::Show, TextPattern::fromText('SHOW TABLES'), [], [], new CallSite('src/e.php', 1, 'App\\R::find', 'pdo.query'), []),
            new CatalogEntry('f1', StatementKind::Select, TextPattern::fromText('SELECT 1 FROM t1'), ['t1'], [], new CallSite('src/f.php', 1, 'App\\F::a', 'pdo.query'), []),
            new CatalogEntry('f2', StatementKind::Select, TextPattern::fromText('SELECT 2 FROM t2'), ['t2'], [], new CallSite('src/f.php', 2, 'App\\F::b', 'pdo.query'), []),
            new CatalogEntry('f3', StatementKind::Select, TextPattern::fromText('SELECT 3 FROM t3'), ['t3'], [], new CallSite('src/f.php', 3, 'App\\G::a', 'pdo.query'), []),
            new CatalogEntry('f4', StatementKind::Select, TextPattern::fromText('SELECT 4 FROM t4'), ['t4'], [], new CallSite('src/f.php', 4, 'App\\H::a', 'pdo.query'), []),
            new CatalogEntry('f5', StatementKind::Select, TextPattern::fromText('SELECT 5 FROM t5'), ['t5'], [], new CallSite('src/g.php', 1, 'App\\I::a', 'pdo.query'), []),
            new CatalogEntry('f6', StatementKind::Select, TextPattern::fromText('SELECT 6 FROM t6'), ['t6'], [], new CallSite('src/h.php', 1, 'App\\J::a', 'pdo.query'), []),
            new CatalogEntry('f7', StatementKind::Select, TextPattern::fromText('SELECT 7 FROM t7'), ['t7'], [], new CallSite('src/i.php', 1, 'App\\K::a', 'pdo.query'), [
                Finding::of(FindingRule::DynamicSql, 'dyn'),
            ]),
            new CatalogEntry('f8', StatementKind::Select, TextPattern::fromText('SELECT 8 FROM t8'), ['t8'], [], new CallSite('src/j.php', 1, 'App\\L::a', 'pdo.query'), [
                Finding::of(FindingRule::DynamicSql, 'dyn'),
            ]),
            new CatalogEntry('f9', StatementKind::Select, TextPattern::fromText('SELECT 9 FROM t9'), ['t9'], [], new CallSite('src/k.php', 1, 'App\\M::a', 'pdo.query'), [
                Finding::of(FindingRule::DynamicSql, 'dyn'),
            ]),
        ];
        $catalog = new Catalog($entries, [new AnalysisProblem('src/broken.php', 'broken')]);
        $site = new ReportSite($catalog);

        self::assertSame(
            '<div class="facets"><input type="search" class="facet-search" placeholder="Narrow by text…" autocomplete="off" spellcheck="false"><span clas'
                . 's="facet-group"><button type="button" class="chip facet k-select" data-facet="kind" data-value="select">SELECT<span class="facet-count">12</'
                . 'span></button><button type="button" class="chip facet k-insert" data-facet="kind" data-value="insert">INSERT<span class="facet-count">1</spa'
                . 'n></button><button type="button" class="chip facet k-update" data-facet="kind" data-value="update">UPDATE<span class="facet-count">1</span><'
                . '/button><button type="button" class="chip facet k-delete" data-facet="kind" data-value="delete">DELETE<span class="facet-count">1</span></bu'
                . 'tton><button type="button" class="chip facet k-schema" data-facet="kind" data-value="alter">ALTER<span class="facet-count">1</span></button>'
                . '<button type="button" class="chip facet k-other" data-facet="kind" data-value="unknown">UNKNOWN<span class="facet-count">1</span></button><b'
                . 'utton type="button" class="chip facet k-other" data-facet="kind" data-value="show">SHOW<span class="facet-count">1</span></button></span><sp'
                . 'an class="facet-group"><button type="button" class="chip facet s-ok" data-facet="resolution" data-value="resolved">resolved<span class="face'
                . 't-count">15</span></button><button type="button" class="chip facet s-danger" data-facet="resolution" data-value="external-input">external-in'
                . 'put<span class="facet-count">1</span></button><button type="button" class="chip facet s-open" data-facet="resolution" data-value="incomplete'
                . '">incomplete<span class="facet-count">1</span></button><button type="button" class="chip facet s-neutral" data-facet="resolution" data-value'
                . '="not-analyzed">not-analyzed<span class="facet-count">1</span></button></span><span class="facet-group"><button type="button" class="chip fa'
                . 'cet s-warn" data-facet="severity" data-value="medium">medium<span class="facet-count">5</span></button><button type="button" class="chip fac'
                . 'et s-neutral" data-facet="severity" data-value="low">low<span class="facet-count">2</span></button><button type="button" class="chip facet s'
                . '-danger" data-facet="severity" data-value="high">high<span class="facet-count">1</span></button></span><span class="facet-shown" data-total='
                . '"18"></span><button type="button" class="facet-clear" hidden>Clear</button></div>',
            (new StatementList())->facets($entries),
        );
    }
}
