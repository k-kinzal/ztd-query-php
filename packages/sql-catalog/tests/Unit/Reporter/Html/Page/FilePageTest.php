<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Html\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Catalog\CallSite;
use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Catalog\Resolution;
use SqlCatalog\Catalog\Severity;
use SqlCatalog\Catalog\StatementPart;
use SqlCatalog\Reporter\Html\CatalogIndex;
use SqlCatalog\Reporter\Html\CatalogStatistics;
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\Page\FilePage;
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
#[UsesClass(Severity::class)]
#[UsesClass(SqlHighlighter::class)]
#[UsesClass(StatementKind::class)]
#[UsesClass(StatementList::class)]
#[UsesClass(StatementPart::class)]
#[UsesClass(StatementRow::class)]
#[UsesClass(TableName::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextPattern::class)]
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
            '<h2 id="tables">Tables</h2><ol class="route-top route-chips"><li><a class="chip chip-ghost" href="../tables/users.html">users</a><span class="route-figures">1</span></li></ol>',
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
}
