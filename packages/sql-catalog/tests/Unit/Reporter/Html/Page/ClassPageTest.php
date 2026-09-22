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
use SqlCatalog\Reporter\Html\Page\ClassPage;
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

#[CoversClass(ClassPage::class)]
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
final class ClassPageTest extends TestCase
{
    public function testRenderListsTheStatementsMethodByMethod(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['users'], [], new CallSite('src/a.php', 9, 'App\\R::find', 'pdo.query'), []),
            new CatalogEntry('a2', StatementKind::Insert, TextPattern::fromText('INSERT'), ['users'], [], new CallSite('src/a.php', 3, 'App\\R::add', 'pdo.query'), []),
        ]);
        $page = (new ClassPage())->render(new ReportSite($catalog), 'App\\R');

        self::assertStringContainsString('<h1><code>R</code><span class="count">2 statements</span></h1>', $page);
        self::assertStringContainsString('2 statements in 2 methods of <code>App\\R</code>, written in <a href="../files/src-a-php.html">src/a.php</a>.', $page);
        self::assertStringContainsString('<a class="chip chip-ghost" href="../tables/users.html">users</a><span class="route-figures">2</span>', $page);
        self::assertMatchesRegularExpression('/id="fn-app-r-add".*id="fn-app-r-find"/s', $page);
    }

    public function testTablesSaySoWhenNoneIsNamed(): void
    {
        self::assertSame('<h2 id="tables">Tables</h2><p class="none">No statement here names a table.</p>', (new ClassPage())->tables(new ReportSite(new Catalog()), []));
    }

    public function testMethodsAreInTheOrderTheyAreWritten(): void
    {
        $entries = [
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 9, 'R::find', 'pdo.query'), []),
            new CatalogEntry('a2', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('a.php', 3, 'R::add', 'pdo.query'), []),
        ];

        self::assertSame(['R::add', 'R::find'], array_keys((new ClassPage())->methods($entries)));
    }

    public function testSectionsHeadEachMethodWithWhereItStarts(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 9, 'R::find', 'pdo.query'), []);
        $site = new ReportSite(new Catalog([$entry]));

        self::assertStringStartsWith(
            '<section class="group" id="fn-r-find"><h3><code>R::find</code><span class="count">1</span><span class="muted">a.php:9</span><a class="anchor" href="#fn-r-find">#</a></h3><ol class="rows">',
            (new ClassPage())->sections($site, 'classes/r.html', ['R::find' => [$entry]]),
        );
    }

    public function testAnchorsNameTheTablesAndEveryMethod(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 9, 'R::find', 'pdo.query'), []);

        self::assertSame([['Tables', 'tables'], ['find', 'fn-r-find']], (new ClassPage())->anchors(new ReportSite(new Catalog([$entry])), [$entry]));
    }
}
