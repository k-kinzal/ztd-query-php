<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Html\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Catalog\AnalysisProblem;
use SqlCatalog\Core\Catalog\CallSite;
use SqlCatalog\Core\Catalog\Catalog;
use SqlCatalog\Core\Catalog\CatalogEntry;
use SqlCatalog\Core\Catalog\Finding;
use SqlCatalog\Core\Catalog\FindingRule;
use SqlCatalog\Core\Catalog\Placeholder;
use SqlCatalog\Core\Catalog\Resolution;
use SqlCatalog\Core\Catalog\Severity;
use SqlCatalog\Core\Catalog\StatementPart;
use SqlCatalog\Core\Catalog\ValueDomain;
use SqlCatalog\Core\Sql\StatementKind;
use SqlCatalog\Core\Text\LiteralText;
use SqlCatalog\Core\Text\Origin;
use SqlCatalog\Core\Text\TextHole;
use SqlCatalog\Core\Text\TextPattern;
use SqlCatalog\Core\Type\TypeShape;
use SqlCatalog\Reporter\Html\CatalogIndex;
use SqlCatalog\Reporter\Html\CatalogStatistics;
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\Page\FileIndexPage;
use SqlCatalog\Reporter\Html\Palette;
use SqlCatalog\Reporter\Html\ReportSite;
use SqlCatalog\Reporter\Html\Scope;
use SqlCatalog\Reporter\Html\SqlFormatter;
use SqlCatalog\Reporter\Html\SqlHighlighter;
use SqlCatalog\Reporter\Html\StatementList;
use SqlCatalog\Reporter\Html\StatementRow;
use SqlCatalog\Reporter\Html\TableName;

#[CoversClass(FileIndexPage::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(CatalogIndex::class)]
#[UsesClass(CatalogStatistics::class)]
#[UsesClass(HtmlText::class)]
#[UsesClass(ReportSite::class)]
#[UsesClass(Scope::class)]
#[UsesClass(Severity::class)]
#[UsesClass(StatementKind::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(AnalysisProblem::class)]
#[UsesClass(Finding::class)]
#[UsesClass(FindingRule::class)]
#[UsesClass(Placeholder::class)]
#[UsesClass(ValueDomain::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(StatementPart::class)]
#[UsesClass(Palette::class)]
#[UsesClass(SqlFormatter::class)]
#[UsesClass(SqlHighlighter::class)]
#[UsesClass(StatementList::class)]
#[UsesClass(StatementRow::class)]
#[UsesClass(TableName::class)]
#[UsesClass(Origin::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TypeShape::class)]
final class FileIndexPageTest extends TestCase
{
    public function testRenderListsFilesUnderTheirDirectories(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('src/a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('b1', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('index.php', 1, 'f', 'pdo.query'), []),
        ]);
        $page = (new FileIndexPage())->render(new ReportSite($catalog, formatter: \SqlCatalog\Facade\Builtins::sqlFormatter()));

        self::assertStringContainsString('<h1>Files<span class="count">2 files</span></h1>', $page);
        self::assertStringContainsString('<h2 id="dir-root"><code>(root)</code><span class="count">1 file</span></h2>', $page);
        self::assertStringContainsString('<h2 id="dir-src"><code>src/</code><span class="count">1 file</span></h2>', $page);
        self::assertStringContainsString('No statement was found.', (new FileIndexPage())->render(new ReportSite(new Catalog(), formatter: \SqlCatalog\Facade\Builtins::sqlFormatter())));
    }

    public function testSectionIsASortableTableOfTheDirectorysFiles(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('src/a.php', 1, 'f', 'pdo.query'), []),
        ]);
        $section = (new FileIndexPage())->section(new ReportSite($catalog, formatter: \SqlCatalog\Facade\Builtins::sqlFormatter()), 'src', ['src/a.php']);

        self::assertStringContainsString('<table class="sortable filter-target" data-dd-sortable><thead><tr><th scope="col" data-dd-sort="text">File</th>', $section);
        self::assertStringContainsString('href="files/src-a-php.html">a.php</a>', $section);
    }

    public function testRowCountsWhatWasFoundInTheFile(): void
    {
        $entries = [
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['users'], [], new CallSite('src/a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('a2', StatementKind::Select, TextPattern::fromText('SELECT 2'), ['users'], [], new CallSite('src/a.php', 2, 'g', 'pdo.query'), []),
        ];

        self::assertSame(
            '<tr><td><a class="mono" href="files/src-a-php.html">a.php</a></td><td class="num">2</td><td class="num">2</td><td class="num">1</td><td class="num"><span class="none">0</span></td></tr>',
            (new FileIndexPage())->row(new ReportSite(new Catalog($entries), formatter: \SqlCatalog\Facade\Builtins::sqlFormatter()), 'src/a.php', $entries),
        );
    }

    public function testAnchorsNameEveryDirectory(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('src/a.php', 1, 'f', 'pdo.query'), []),
        ]);

        self::assertSame([['src/', 'dir-src']], (new FileIndexPage())->anchors(new ReportSite($catalog, formatter: \SqlCatalog\Facade\Builtins::sqlFormatter())));
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
        $site = new ReportSite($catalog, formatter: \SqlCatalog\Facade\Builtins::sqlFormatter());

        self::assertSame(
            '<h1>Files<span class="count">11 files</span></h1><p class="lede">Every file a statement is written in, by directory. Open a file to read its statement'
                . 's function by function.</p><input type="search" name="filter" class="input input-block" data-filter-rows placeholder="Narrow by file name…" aria-label'
                . '="Narrow by file name" autocomplete="off" spellcheck="false"><section class="group"><h2 id="dir-lib"><code>lib/</code><span class="count">1 file</span'
                . '></h2><div class="table-wrap"><table class="sortable filter-target" data-dd-sortable><thead><tr><th scope="col" data-dd-sort="text">File</th><th scope'
                . '="col" class="num" data-dd-sort="number">Statements</th><th scope="col" class="num" data-dd-sort="number">Functions</th><th scope="col" class="num" da'
                . 'ta-dd-sort="number">Tables</th><th scope="col" class="num" data-dd-sort="number">Attention</th></tr></thead><tbody><tr><td><a class="mono" href="files'
                . '/lib-c-php.html">c.php</a></td><td class="num">2</td><td class="num">2</td><td class="num"><span class="none">0</span></td><td class="num"><span class'
                . '="none">0</span></td></tr></tbody></table></div></section><section class="group"><h2 id="dir-src"><code>src/</code><span class="count">10 files</span>'
                . '</h2><div class="table-wrap"><table class="sortable filter-target" data-dd-sortable><thead><tr><th scope="col" data-dd-sort="text">File</th><th scope='
                . '"col" class="num" data-dd-sort="number">Statements</th><th scope="col" class="num" data-dd-sort="number">Functions</th><th scope="col" class="num" dat'
                . 'a-dd-sort="number">Tables</th><th scope="col" class="num" data-dd-sort="number">Attention</th></tr></thead><tbody><tr><td><a class="mono" href="files/'
                . 'src-a-php.html">a.php</a></td><td class="num">3</td><td class="num">2</td><td class="num">1</td><td class="num">2</td></tr><tr><td><a class="mono" hre'
                . 'f="files/src-b-php.html">b.php</a></td><td class="num">2</td><td class="num">2</td><td class="num">1</td><td class="num"><span class="none">0</span></'
                . 'td></tr><tr><td><a class="mono" href="files/src-d-php.html">d.php</a></td><td class="num">1</td><td class="num">1</td><td class="num">2</td><td class='
                . '"num">1</td></tr><tr><td><a class="mono" href="files/src-e-php.html">e.php</a></td><td class="num">1</td><td class="num">1</td><td class="num"><span c'
                . 'lass="none">0</span></td><td class="num"><span class="none">0</span></td></tr><tr><td><a class="mono" href="files/src-f-php.html">f.php</a></td><td cl'
                . 'ass="num">4</td><td class="num">4</td><td class="num">4</td><td class="num"><span class="none">0</span></td></tr><tr><td><a class="mono" href="files/s'
                . 'rc-g-php.html">g.php</a></td><td class="num">1</td><td class="num">1</td><td class="num">1</td><td class="num"><span class="none">0</span></td></tr><t'
                . 'r><td><a class="mono" href="files/src-h-php.html">h.php</a></td><td class="num">1</td><td class="num">1</td><td class="num">1</td><td class="num"><spa'
                . 'n class="none">0</span></td></tr><tr><td><a class="mono" href="files/src-i-php.html">i.php</a></td><td class="num">1</td><td class="num">1</td><td cla'
                . 'ss="num">1</td><td class="num">1</td></tr><tr><td><a class="mono" href="files/src-j-php.html">j.php</a></td><td class="num">1</td><td class="num">1</t'
                . 'd><td class="num">1</td><td class="num">1</td></tr><tr><td><a class="mono" href="files/src-k-php.html">k.php</a></td><td class="num">1</td><td class="'
                . 'num">1</td><td class="num">1</td><td class="num">1</td></tr></tbody></table></div></section>',
            (new FileIndexPage())->render($site),
        );
    }
}
