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
use SqlCatalog\Catalog\Resolution;
use SqlCatalog\Catalog\Severity;
use SqlCatalog\Catalog\StatementPart;
use SqlCatalog\Reporter\Html\CatalogIndex;
use SqlCatalog\Reporter\Html\CatalogStatistics;
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\Page\TablePage;
use SqlCatalog\Reporter\Html\Palette;
use SqlCatalog\Reporter\Html\ReportSite;
use SqlCatalog\Reporter\Html\Scope;
use SqlCatalog\Reporter\Html\SqlHighlighter;
use SqlCatalog\Reporter\Html\StatementList;
use SqlCatalog\Reporter\Html\StatementRow;
use SqlCatalog\Reporter\Html\TableName;
use SqlCatalog\Sql\StatementKind;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\TextPattern;

#[CoversClass(TablePage::class)]
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
#[UsesClass(StatementList::class)]
#[UsesClass(StatementPart::class)]
#[UsesClass(StatementRow::class)]
#[UsesClass(TableName::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextPattern::class)]
final class TablePageTest extends TestCase
{
    public function testRenderSaysWhatUsesTheTableAndListsItsStatementsByWhatTheyDo(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1 FROM users'), ['users', 'posts'], [], new CallSite('a.php', 1, 'App\\R::find', 'pdo.query'), []),
            new CatalogEntry('a2', StatementKind::Insert, TextPattern::fromText('INSERT INTO users'), ['users'], [], new CallSite('a.php', 2, 'helper', 'pdo.query'), []),
        ]);
        $page = (new TablePage())->render(new ReportSite($catalog), 'users');

        self::assertStringContainsString('<h1><code>users</code><span class="count">2 statements</span></h1>', $page);
        self::assertStringContainsString('read by 1 statement, written by 1 statement.', $page);
        self::assertStringContainsString('<h2 id="used-from">Used from<span class="count">2 functions</span></h2>', $page);
        self::assertStringContainsString('<h2 id="alongside">Named alongside</h2>', $page);
        self::assertStringContainsString('<section class="group" id="writes"><h3>Writes<span class="count">1</span></h3>', $page);
        self::assertStringContainsString('<section class="group" id="reads"><h3>Reads<span class="count">1</span></h3>', $page);
        self::assertStringContainsString('href="../statements/a2.html"', $page);
    }

    public function testSummaryMentionsWhatNeedsAttention(): void
    {
        $summary = (new TablePage())->summary(new TableName('{$}'), ['reads' => 2, 'writes' => 0, 'schema' => 1, 'other' => 0, 'attention' => 1]);

        self::assertSame('A table whose name the analysis could not pin down, read by 2 statements, altered by 1 statement. 1 statement carry a finding worth looking at.', $summary);
    }

    public function testUsedFromLinksEachFunctionAndSaysWhatItDoes(): void
    {
        $entries = [
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['users'], [], new CallSite('src/a.php', 1, 'App\\R::find', 'pdo.query'), []),
            new CatalogEntry('a2', StatementKind::Delete, TextPattern::fromText('DELETE'), ['users'], [], new CallSite('src/a.php', 2, 'App\\R::find', 'pdo.query'), []),
        ];
        $site = new ReportSite(new Catalog($entries));

        self::assertStringContainsString(
            '<tr><td><a class="mono" href="../classes/app-r.html#fn-app-r-find">R::find</a></td><td><a class="muted" href="../files/src-a-php.html">src/a.php</a></td>'
            . '<td class="num">2</td><td class="tight">reads, writes</td></tr>',
            (new TablePage())->usedFrom($site, $entries),
        );
    }

    public function testVerbsNameEveryUseFound(): void
    {
        self::assertSame('reads, alters, other', (new TablePage())->verbs(['reads' => 1, 'writes' => 0, 'schema' => 2, 'other' => 1, 'attention' => 0]));
    }

    public function testAlongsideIsSilentWhenTheTableIsNamedAlone(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['users'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
        ]);

        self::assertSame('', (new TablePage())->alongside(new ReportSite($catalog), 'users'));
    }

    public function testGroupsListOnlyTheUsesPresent(): void
    {
        $entries = [
            new CatalogEntry('a1', StatementKind::Show, TextPattern::fromText('SHOW TABLES'), ['users'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
        ];
        $groups = (new TablePage())->groups(new ReportSite(new Catalog($entries)), 'users', $entries);

        self::assertStringStartsWith('<section class="group" id="other"><h3>Other statements<span class="count">1</span></h3>', $groups);
        self::assertStringNotContainsString('id="reads"', $groups);
    }

    public function testAnchorsFollowTheSectionsPresent(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['users', 'posts'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []),
            new CatalogEntry('a2', StatementKind::Update, TextPattern::fromText('UPDATE'), ['users'], [], new CallSite('a.php', 2, 'f', 'pdo.query'), []),
        ]);

        self::assertSame(
            [['Used from', 'used-from'], ['Named alongside', 'alongside'], ['Writes', 'writes'], ['Reads', 'reads']],
            (new TablePage())->anchors(new CatalogIndex($catalog), 'users'),
        );
    }
}
