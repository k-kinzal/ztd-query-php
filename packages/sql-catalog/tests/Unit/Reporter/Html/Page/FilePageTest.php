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
use SqlCatalog\Reporter\Html\Page\FilePage;
use SqlCatalog\Reporter\Html\Palette;
use SqlCatalog\Reporter\Html\ReportSite;
use SqlCatalog\Reporter\Html\Scope;
use SqlCatalog\Reporter\Html\Source\SourceCode;
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

#[CoversClass(FilePage::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(CatalogIndex::class)]
#[UsesClass(CatalogStatistics::class)]
#[UsesClass(HtmlText::class)]
#[UsesClass(Palette::class)]
#[UsesClass(ReportSite::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Scope::class)]
#[UsesClass(SourceCode::class)]
#[UsesClass(Severity::class)]
#[UsesClass(SqlHighlighter::class)]
#[UsesClass(StatementKind::class)]
#[UsesClass(StatementList::class)]
#[UsesClass(StatementPart::class)]
#[UsesClass(StatementRow::class)]
#[UsesClass(TableName::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(AnalysisProblem::class)]
#[UsesClass(Finding::class)]
#[UsesClass(FindingRule::class)]
#[UsesClass(Placeholder::class)]
#[UsesClass(ValueDomain::class)]
#[UsesClass(SqlFormatter::class)]
#[UsesClass(Origin::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TypeShape::class)]
final class FilePageTest extends TestCase
{
    public function testRenderListsTheStatementsFunctionByFunction(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['users'], [], new CallSite('src/a.php', 9, 'helper', 'pdo.query'), []),
            new CatalogEntry('a2', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('src/a.php', 1, '{main}', 'pdo.query'), []),
        ]);
        $page = (new FilePage())->render(new ReportSite($catalog), 'src/a.php');

        self::assertStringContainsString('<h1><code>src/a.php</code><span class="count">2 statements</span></h1>', $page);
        self::assertStringContainsString('2 statements issued from 2 functions in this file.', $page);
        self::assertMatchesRegularExpression('/id="fn-main".*id="fn-helper"/s', $page);
    }

    public function testTablesLinkToTheTablesNamed(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['users'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []);

        self::assertSame(
            '<h2 id="tables">Tables</h2><div class="chips"><a class="chip chip-ghost" href="../tables/users.html">users<span class="facet-count">1</span></a></div>',
            (new FilePage())->tables(new ReportSite(new Catalog([$entry])), [$entry]),
        );
    }

    public function testFunctionsAreInTheOrderTheyAreWritten(): void
    {
        $entries = [
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 9, 'later', 'pdo.query'), []),
            new CatalogEntry('a2', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('a.php', 3, 'earlier', 'pdo.query'), []),
        ];

        self::assertSame(['earlier', 'later'], array_keys((new FilePage())->functions($entries)));
    }

    public function testSectionsLeadFromAMethodToItsClass(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 9, 'App\\R::find', 'pdo.query'), []);
        $site = new ReportSite(new Catalog([$entry]));

        self::assertStringStartsWith(
            '<section class="group" id="fn-app-r-find"><h3><a class="mono" href="../classes/app-r.html">R::find</a><span class="count">1</span><span class="muted">line 9</span>',
            (new FilePage())->sections($site, 'a.php', ['App\\R::find' => [$entry]]),
        );
    }

    public function testAnchorsNameTheTablesAndEveryFunction(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 9, '{main}', 'pdo.query'), []);

        self::assertSame([['Tables', 'tables'], ['top-level code', 'fn-main']], (new FilePage())->anchors(new ReportSite(new Catalog([$entry])), [$entry]));
    }


    public function testContextListsTheFunctionsAndTheFilesOfTheSameDirectory(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('src/a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('b1', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('src/b.php', 1, 'g', 'pdo.query'), []),
            new CatalogEntry('c1', StatementKind::Select, TextPattern::fromText('SELECT 3'), [], [], new CallSite('lib/c.php', 1, 'h', 'pdo.query'), []),
        ]);

        self::assertSame(
            [
                ['On this page', [['Tables', '#tables', null, false], ['f', '#fn-f', null, false]], null],
                ['Files in src/', [['a.php', 'files/src-a-php.html', 1, true], ['b.php', 'files/src-b-php.html', 1, false]], 'files.html'],
            ],
            (new FilePage())->context(new ReportSite($catalog), 'src/a.php'),
        );
    }

    public function testContextNamesTheRootDirectory(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('index.php', 1, '{main}', 'pdo.query'), []),
        ]);

        self::assertSame(
            ['Files in (root)', [['index.php', 'files/index-php.html', 1, true]], 'files.html'],
            (new FilePage())->context(new ReportSite($catalog), 'index.php')[1],
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
            '<h1><code>lib/c.php</code><span class="count">2 statements</span></h1><p class="lede">2 statements issued from 2 functions in this file.</p><h2 id="ta'
                . 'bles">Tables</h2><p class="empty-inline">No statement here names a table.</p><h2 id="functions">Functions</h2><div data-narrowable><div class="facets"'
                . ' role="group" aria-label="Narrow the listing"><input type="search" name="narrow" class="input facet-search" placeholder="Narrow by text…" aria-label="'
                . 'Narrow by text" autocomplete="off" spellcheck="false"><span class="facet-group"><button type="button" class="chip facet tone-blue" data-facet="kind" d'
                . 'ata-value="select" aria-pressed="false">SELECT<span class="facet-count">1</span></button><button type="button" class="chip facet tone-slate" data-face'
                . 't="kind" data-value="unknown" aria-pressed="false">UNKNOWN<span class="facet-count">1</span></button></span><span class="facet-group"><button type="bu'
                . 'tton" class="chip facet chip-ghost" data-facet="resolution" data-value="incomplete" aria-pressed="false">incomplete<span class="facet-count">1</span><'
                . '/button><button type="button" class="chip facet tone-neutral" data-facet="resolution" data-value="not-analyzed" aria-pressed="false">not-analyzed<span'
                . ' class="facet-count">1</span></button></span><span class="facet-shown" aria-live="polite"></span><button type="button" class="btn facet-clear" hidden>'
                . 'Clear</button></div><section class="group" id="fn-helper"><h3><code>helper</code><span class="count">1</span><span class="muted">line 2</span><a class'
                . '="anchor" href="#fn-helper">#</a></h3><ol class="rows"><li class="row" data-kind="select" data-resolution="incomplete" data-severity="low" data-rule="'
                . 'analysis-incomplete" data-sink="mysqli.query" data-open="open" data-table="" data-namespace="" data-class="" data-function="helper" data-file="lib/c.p'
                . 'hp"><a class="row-main" href="../statements/c1.html"><span class="chip tone-blue">SELECT</span><span class="row-body"><span class="hole tone-warn" tit'
                . 'le="This is a gap: a dependency the analyzer stopped following fills it.">{$}</span></span></a><p class="row-meta"><span class="chip chip-ghost" title'
                . '="A cycle or an analysis budget stopped the search before it closed.">incomplete</span></p></li></ol></section><section class="group" id="fn-main"><h3'
                . '><code>top-level code</code><span class="count">1</span><span class="muted">line 6</span><a class="anchor" href="#fn-main">#</a></h3><ol class="rows">'
                . '<li class="row" data-kind="unknown" data-resolution="not-analyzed" data-severity="low" data-rule="call-not-analyzed" data-sink="unmatched" data-open="'
                . 'open" data-table="" data-namespace="" data-class="" data-function="{main}" data-file="lib/c.php"><a class="row-main" href="../statements/c2.html"><spa'
                . 'n class="chip tone-slate">UNKNOWN</span><span class="row-body"><span class="tok-com">no statement was read from this call</span> $db-&gt;query($sql)</'
                . 'span></a><p class="row-meta"><span class="chip tone-neutral" title="The call was found but never examined, so nothing was read from it.">not-analyzed<'
                . '/span></p></li></ol></section></div>',
            (new FilePage())->render($site, 'lib/c.php'),
        );
    }

    public function testProblemsExplainsWhyThisFileCouldNotBeParsed(): void
    {
        $site = new ReportSite(new Catalog([], [new AnalysisProblem('broken.php', 'Unexpected <token>'), new AnalysisProblem('other.php', 'Other problem')]));
        $page = new FilePage();

        self::assertStringContainsString('Unexpected &lt;token&gt;', $page->problems($site, 'broken.php'));
        self::assertStringNotContainsString('Other problem', $page->problems($site, 'broken.php'));
        self::assertSame('', $page->problems($site, 'valid.php'));
    }

}
