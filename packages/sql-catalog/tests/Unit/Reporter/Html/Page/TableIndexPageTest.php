<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Html\Page;

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
use SqlCatalog\Reporter\Html\Page\TableIndexPage;
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

#[CoversClass(TableIndexPage::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(CatalogIndex::class)]
#[UsesClass(CatalogStatistics::class)]
#[UsesClass(Finding::class)]
#[UsesClass(FindingRule::class)]
#[UsesClass(HtmlText::class)]
#[UsesClass(ReportSite::class)]
#[UsesClass(Scope::class)]
#[UsesClass(Severity::class)]
#[UsesClass(TableName::class)]
#[UsesClass(StatementKind::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(AnalysisProblem::class)]
#[UsesClass(Placeholder::class)]
#[UsesClass(ValueDomain::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(StatementPart::class)]
#[UsesClass(Palette::class)]
#[UsesClass(SqlFormatter::class)]
#[UsesClass(SqlHighlighter::class)]
#[UsesClass(StatementList::class)]
#[UsesClass(StatementRow::class)]
#[UsesClass(Origin::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TypeShape::class)]
final class TableIndexPageTest extends TestCase
{
    public function testRenderGroupsTablesBySchemaWhenAnyIsQualified(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['users', 'app.orders'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
        ]);
        $page = (new TableIndexPage())->render(new ReportSite($catalog));

        self::assertStringContainsString('<h1>Tables<span class="count">2 tables</span></h1>', $page);
        self::assertStringContainsString('<h2 id="schema-unqualified">Unqualified<span class="count">1 table</span></h2>', $page);
        self::assertStringContainsString('<h2 id="schema-app">app<span class="count">1 table</span></h2>', $page);
        self::assertStringContainsString('No statement names a table.', (new TableIndexPage())->render(new ReportSite(new Catalog())));
    }

    public function testRenderDoesNotGroupWhenNoTableIsQualified(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['users'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
        ]);

        self::assertStringNotContainsString('Unqualified', (new TableIndexPage())->render(new ReportSite($catalog)));
    }

    public function testBySchemaPutsUnqualifiedTablesUnderAnEmptyName(): void
    {
        self::assertSame(['', 'app'], array_keys((new TableIndexPage())->bySchema(['app.orders' => [], 'users' => []])));
    }

    public function testTableIsSortableByEveryColumn(): void
    {
        $table = (new TableIndexPage())->table(new ReportSite(new Catalog()), []);

        self::assertStringContainsString('<table class="sortable filter-target" data-dd-sortable><thead><tr><th scope="col" data-dd-sort="text">Table</th><th scope="col" class="num" data-dd-sort="number">Statements</th>', $table);
    }

    public function testRowCountsHowTheTableIsUsedAndMarksAGapInItsName(): void
    {
        $entries = [
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['{$}users'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), [Finding::of(FindingRule::DynamicSql, 'x')]),
            new CatalogEntry('a2', StatementKind::Alter, TextPattern::fromText('ALTER'), ['{$}users'], [], new CallSite('a.php', 2, 'g', 'pdo.query'), []),
        ];
        $site = new ReportSite(new Catalog($entries));

        self::assertSame(
            '<tr><td><a class="mono" href="tables/users.html"><span class="hole tone-warn" title="A part of this name the analysis could not pin down">{$}</span>us'
                . 'ers</a></td><td class="num">2</td><td class="num">1</td><td class="num"><span class="none">0</span></td><td class="num">1</td><td class="num">1</td><t'
                . 'd class="num">2</td></tr>',
            (new TableIndexPage())->row($site, '{$}users', $entries),
        );
    }

    public function testRenderIsWrittenExactly(): void
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
            '<h1>Tables<span class="count">11 tables</span></h1><p class="lede">Every table the statements name, most named first. A join counts for each of its ta'
                . 'bles. Open a table to see what reads, writes and alters it, and from where.</p><input type="search" name="filter" class="input input-block" data-filte'
                . 'r-rows placeholder="Narrow by table name…" aria-label="Narrow by table name" autocomplete="off" spellcheck="false"><div class="table-wrap"><table clas'
                . 's="sortable filter-target" data-dd-sortable><thead><tr><th scope="col" data-dd-sort="text">Table</th><th scope="col" class="num" data-dd-sort="number"'
                . '>Statements</th><th scope="col" class="num" data-dd-sort="number">Reads</th><th scope="col" class="num" data-dd-sort="number">Writes</th><th scope="co'
                . 'l" class="num" data-dd-sort="number">Schema</th><th scope="col" class="num" data-dd-sort="number">Attention</th><th scope="col" class="num" data-dd-so'
                . 'rt="number">Functions</th></tr></thead><tbody><tr><td><a class="mono" href="tables/posts.html">posts</a></td><td class="num">4</td><td class="num">2</'
                . 'td><td class="num">2</td><td class="num"><span class="none">0</span></td><td class="num">3</td><td class="num">2</td></tr><tr><td><a class="mono" href'
                . '="tables/users.html">users</a></td><td class="num">3</td><td class="num">1</td><td class="num">1</td><td class="num">1</td><td class="num">1</td><td c'
                . 'lass="num">3</td></tr><tr><td><a class="mono" href="tables/t1.html">t1</a></td><td class="num">1</td><td class="num">1</td><td class="num"><span class'
                . '="none">0</span></td><td class="num"><span class="none">0</span></td><td class="num"><span class="none">0</span></td><td class="num">1</td></tr><tr><t'
                . 'd><a class="mono" href="tables/t2.html">t2</a></td><td class="num">1</td><td class="num">1</td><td class="num"><span class="none">0</span></td><td cla'
                . 'ss="num"><span class="none">0</span></td><td class="num"><span class="none">0</span></td><td class="num">1</td></tr><tr><td><a class="mono" href="tabl'
                . 'es/t3.html">t3</a></td><td class="num">1</td><td class="num">1</td><td class="num"><span class="none">0</span></td><td class="num"><span class="none">'
                . '0</span></td><td class="num"><span class="none">0</span></td><td class="num">1</td></tr><tr><td><a class="mono" href="tables/t4.html">t4</a></td><td c'
                . 'lass="num">1</td><td class="num">1</td><td class="num"><span class="none">0</span></td><td class="num"><span class="none">0</span></td><td class="num"'
                . '><span class="none">0</span></td><td class="num">1</td></tr><tr><td><a class="mono" href="tables/t5.html">t5</a></td><td class="num">1</td><td class="'
                . 'num">1</td><td class="num"><span class="none">0</span></td><td class="num"><span class="none">0</span></td><td class="num"><span class="none">0</span>'
                . '</td><td class="num">1</td></tr><tr><td><a class="mono" href="tables/t6.html">t6</a></td><td class="num">1</td><td class="num">1</td><td class="num"><'
                . 'span class="none">0</span></td><td class="num"><span class="none">0</span></td><td class="num"><span class="none">0</span></td><td class="num">1</td><'
                . '/tr><tr><td><a class="mono" href="tables/t7.html">t7</a></td><td class="num">1</td><td class="num">1</td><td class="num"><span class="none">0</span></'
                . 'td><td class="num"><span class="none">0</span></td><td class="num">1</td><td class="num">1</td></tr><tr><td><a class="mono" href="tables/t8.html">t8</'
                . 'a></td><td class="num">1</td><td class="num">1</td><td class="num"><span class="none">0</span></td><td class="num"><span class="none">0</span></td><td'
                . ' class="num">1</td><td class="num">1</td></tr><tr><td><a class="mono" href="tables/t9.html">t9</a></td><td class="num">1</td><td class="num">1</td><td'
                . ' class="num"><span class="none">0</span></td><td class="num"><span class="none">0</span></td><td class="num">1</td><td class="num">1</td></tr></tbody>'
                . '</table></div>',
            (new TableIndexPage())->render($site),
        );
    }
}
