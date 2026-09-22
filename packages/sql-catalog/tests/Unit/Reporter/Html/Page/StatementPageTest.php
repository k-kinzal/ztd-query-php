<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Html\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
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
use SqlCatalog\Reporter\Html\Page\StatementPage;
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

#[CoversClass(StatementPage::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(CatalogIndex::class)]
#[UsesClass(CatalogStatistics::class)]
#[UsesClass(Finding::class)]
#[UsesClass(FindingRule::class)]
#[UsesClass(HtmlText::class)]
#[UsesClass(Palette::class)]
#[UsesClass(Placeholder::class)]
#[UsesClass(ReportSite::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Scope::class)]
#[UsesClass(Severity::class)]
#[UsesClass(SqlFormatter::class)]
#[UsesClass(SqlHighlighter::class)]
#[UsesClass(StatementKind::class)]
#[UsesClass(StatementList::class)]
#[UsesClass(StatementPart::class)]
#[UsesClass(StatementRow::class)]
#[UsesClass(TableName::class)]
#[UsesClass(ValueDomain::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(Origin::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
final class StatementPageTest extends TestCase
{
    public function testRenderCarriesEverythingKnownAboutTheStatement(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT id FROM users WHERE id = ?'), ['users'], [
            new Placeholder('?', 0, null, new ValueDomain('int', [7], true, [])),
        ], new CallSite('src/a.php', 12, 'App\\R::find', 'pdo.prepare'), [Finding::of(FindingRule::DynamicSql, 'spliced')]);
        $other = new CatalogEntry('a2', StatementKind::Delete, TextPattern::fromText('DELETE FROM users'), ['users'], [], new CallSite('src/a.php', 20, 'App\\R::find', 'pdo.query'), []);
        $page = (new StatementPage())->render(new ReportSite(new Catalog([$entry, $other])), $entry);

        self::assertStringContainsString('<h1><span class="chip k-select">SELECT</span><span>on users</span></h1>', $page);
        self::assertStringContainsString('<pre class="sql sql-full"><span class="tok-kw">SELECT</span> id' . "\n" . '<span class="tok-kw">FROM</span> users', $page);
        self::assertStringContainsString('<div class="split"><section><h2 id="facts">About this statement</h2>', $page);
        self::assertStringContainsString('<h2 id="values">Bound values</h2>', $page);
        self::assertStringContainsString('<h2 id="findings">Findings</h2>', $page);
        self::assertStringContainsString('<h2 id="same-function">Also issued by <code>R::find</code><span class="count">1</span></h2>', $page);
        self::assertStringContainsString('<h2 id="same-table-users">Also on <a class="chip chip-ghost" href="../tables/users.html">users</a><span class="count">1</span></h2>', $page);
    }

    public function testRenderLeavesOutTheValuesWhenTheStatementTakesNone(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, '{main}', 'pdo.query'), []);
        $page = (new StatementPage())->render(new ReportSite(new Catalog([$entry])), $entry);

        self::assertStringNotContainsString('class="split"', $page);
        self::assertStringNotContainsString('Bound values', $page);
        self::assertStringNotContainsString('Also issued by', $page);
    }

    public function testTitleNamesTheTablesOrSaysWhyThereAreNone(): void
    {
        $page = new StatementPage();
        $site = new CallSite('a.php', 1, 'f', 'pdo.query');

        self::assertSame('on {$}posts, users', $page->title(new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['{$}posts', 'users'], [], $site, [])));
        self::assertSame('no table named', $page->title(new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], $site, [])));
        self::assertSame('a call nothing was read from', $page->title(new CatalogEntry('a', StatementKind::Unknown, TextPattern::fromHole(new TextHole(Origin::Unreached, TypeShape::unknown())), [], [], $site, [])));
    }

    public function testWhereLinksEveryPlaceAndNamesThePathTaken(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('src/a.php', 12, 'App\\R::find', 'pdo.prepare'), [], true, ['App\\R::run', 'App\\R::find']);

        self::assertSame(
            'Issued at <a class="mono" href="../files/src-a-php.html">src/a.php:12</a> in <a class="mono" href="../classes/app-r.html#fn-app-r-find">App\\R::find</a>'
            . ' through <span class="chip chip-sm" title="The database call that was matched">pdo.prepare</span>, reached by way of <code>App\\R::run → App\\R::find</code>.',
            (new StatementPage())->where(new ReportSite(new Catalog([$entry])), $entry),
        );
    }

    public function testBodyLaysTheStatementOutAndKeepsTheSourceForm(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText("SELECT 1\n  FROM t"), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []);
        $body = (new StatementPage())->body($entry);

        self::assertStringContainsString('<button type="button" class="copy" data-copy="sql-text" title="Copy the statement">Copy</button>', $body);
        self::assertStringContainsString('<pre class="sql sql-full"><span class="tok-kw">SELECT</span> <span class="tok-num">1</span>' . "\n" . '<span class="tok-kw">FROM</span> t</pre>', $body);
        self::assertStringContainsString('<textarea id="sql-text" hidden readonly>SELECT 1' . "\n" . '  FROM t</textarea>', $body);
        self::assertStringContainsString('<details class="as-written"><summary>As written in the source</summary>', $body);
    }

    public function testBodyDoesNotDressUpACallNoStatementWasReadFrom(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Unknown, TextPattern::fromHole(new TextHole(Origin::Unreached, TypeShape::unknown(), '$db->query($sql)')), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []);

        self::assertSame(
            '<pre class="sql sql-full"><span class="tok-com">-- no statement was read from this call</span>' . "\n" . '$db-&gt;query($sql)</pre>',
            (new StatementPage())->body($entry),
        );
    }

    public function testCaveatsWarnAboutWhatTheStatementDoesNotSay(): void
    {
        $page = new StatementPage();
        $site = new CallSite('a.php', 1, 'f', 'pdo.query');
        $open = new CatalogEntry('a', StatementKind::Select, TextPattern::fromHole(new TextHole(Origin::Budget, TypeShape::unknown())), [], [], $site, [], false);
        $cut = new CatalogEntry('b', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], $site, [], true, [], true);
        $unread = new CatalogEntry('c', StatementKind::Unknown, TextPattern::fromHole(new TextHole(Origin::Unreached, TypeShape::unknown())), [], [], $site, []);

        self::assertStringContainsString('may not be all of them.</li><li>Assembled from parts that vary independently', $page->caveats($open));
        self::assertStringContainsString('A limit on loop passes or on callers cut the search short.', $page->caveats($cut));
        self::assertStringContainsString('never examined', $page->caveats($unread));
        self::assertSame('', $page->caveats(new CatalogEntry('d', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], $site, [])));
    }

    public function testFactsLinkTheTablesAndExplainTheReading(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Select, TextPattern::fromHole(new TextHole(Origin::Budget, TypeShape::unknown())), ['users'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []);
        $facts = (new StatementPage())->facts(new ReportSite(new Catalog([$entry])), $entry);

        self::assertStringContainsString('<dt>Resolution</dt><dd><span class="chip s-warn">incomplete</span>', $facts);
        self::assertStringContainsString('<dt>Search</dt><dd><span class="muted">left open: the listing for this call is a lower bound</span></dd>', $facts);
        self::assertStringContainsString('<dt>Tables</dt><dd><a class="chip chip-ghost" href="../tables/users.html">users</a> </dd>', $facts);
        self::assertStringContainsString('<dt>Identifier</dt><dd><code>a1</code>', $facts);
    }

    public function testValuesListTheBindParameters(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [
            new Placeholder(':id', 0, 'id', new ValueDomain('string', ['a', 'b'], true, [])),
        ], new CallSite('a.php', 3, 'f', 'pdo.query'), []);

        self::assertStringContainsString('<code>&#039;a&#039;|&#039;b&#039;</code>', (new StatementPage())->values($entry));
        self::assertSame('', (new StatementPage())->values(new CatalogEntry('b', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 3, 'f', 'pdo.query'), [])));
    }

    public function testValueRowSaysWhenNothingWasBoundOrPinnedDown(): void
    {
        $page = new StatementPage();

        self::assertSame(
            '<tr><td class="tight"><code>?</code></td><td class="tight"><code>?</code></td><td><span class="none">unbound</span></td></tr>',
            $page->valueRow(new Placeholder('?', 0, null, null)),
        );
        self::assertSame(
            '<tr><td class="tight"><code>?</code></td><td class="tight"><code>string</code></td><td><span class="muted">not pinned down: external</span></td></tr>',
            $page->valueRow(new Placeholder('?', 0, null, new ValueDomain('string', [], false, ['external']))),
        );
    }

    public function testOpenValueSaysSoWhenNothingIsKnownAboutWhereItCameFrom(): void
    {
        self::assertSame('not pinned down', (new StatementPage())->openValue([]));
    }

    public function testFindingsCarryTheirRuleAndSeverity(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 3, 'f', 'pdo.query'), [Finding::of(FindingRule::DynamicSql, 'spliced')]);

        self::assertSame(
            '<h2 id="findings">Findings</h2><ul class="finding-list"><li><span class="chip s-warn">medium</span><span><code>dynamic-sql</code> spliced</span></li></ul>',
            (new StatementPage())->findings($entry),
        );
        self::assertSame('', (new StatementPage())->findings(new CatalogEntry('b', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 3, 'f', 'pdo.query'), [])));
    }

    public function testRelatedPointsAtTheRestWhenThereIsMoreThanItLists(): void
    {
        $entries = array_map(
            static fn (int $n): CatalogEntry => new CatalogEntry('s' . $n, StatementKind::Select, TextPattern::fromText('SELECT ' . $n), ['users'], [], new CallSite('a.php', $n, 'f', 'pdo.query'), []),
            range(1, 10),
        );
        $related = (new StatementPage())->related(new ReportSite(new Catalog($entries)), 'statements/s1.html', $entries[0]);

        self::assertStringContainsString('<a href="../files/a-php.html#fn-f">Every statement of this function</a>', $related);
        self::assertStringContainsString('<a href="../tables/users.html">Every statement on this table</a>', $related);
        self::assertSame(2, substr_count($related, '<ol class="rows">'));
    }
}
