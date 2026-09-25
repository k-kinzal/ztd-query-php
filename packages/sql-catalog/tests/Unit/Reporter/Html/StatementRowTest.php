<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Html;

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
use SqlCatalog\Reporter\Html\Palette;
use SqlCatalog\Reporter\Html\ReportSite;
use SqlCatalog\Reporter\Html\Scope;
use SqlCatalog\Reporter\Html\SqlHighlighter;
use SqlCatalog\Reporter\Html\StatementRow;
use SqlCatalog\Sql\StatementKind;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\Origin;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(StatementRow::class)]
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
#[UsesClass(LiteralText::class)]
#[UsesClass(Origin::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
final class StatementRowTest extends TestCase
{
    public function testRenderLinksTheStatementAndWhereItIsIssuedRelativeToThePage(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1 FROM users'), ['users'], [], new CallSite('src/a.php', 4, 'App\\R::find', 'pdo.query'), []);
        $site = new ReportSite(new Catalog([$entry]));

        self::assertSame(
            '<li class="row" data-kind="select" data-resolution="resolved" data-severity="" data-rule="" data-sink="pdo.query" data-open="" data-table="users" data'
                . '-namespace="App" data-class="App\\R" data-function="App\\R::find" data-file="src/a.php"><a class="row-main" href="../statements/a1.html"><span class="'
                . 'chip tone-blue">SELECT</span><span class="row-body"><span class="tok-kw">SELECT</span> <span class="tok-num">1</span> <span class="tok-kw">FROM</span>'
                . ' users</span></a><p class="row-meta"><a href="../files/src-a-php.html">src/a.php:4</a><a href="../classes/app-r.html#fn-app-r-find">R::find</a><a clas'
                . 's="chip chip-ghost" href="../tables/users.html">users</a></p></li>',
            (new StatementRow())->render($site, 'tables/users.html', $entry),
        );
    }

    public function testAttributesCarryEveryFactAListingIsNarrowedBy(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Select, TextPattern::fromHole(new TextHole(Origin::Budget, TypeShape::unknown())), ['a', 'b'], [], new CallSite('a.php', 4, 'f', 'pdo.query'), [
            Finding::of(FindingRule::DynamicSql, 'x'),
            Finding::of(FindingRule::AnalysisIncomplete, 'y'),
        ]);

        self::assertSame(
            ' data-kind="select" data-resolution="incomplete" data-severity="medium" data-rule="dynamic-sql analysis-incomplete" data-sink="pdo.query" data-open="o'
                . 'pen" data-table="a b" data-namespace="" data-class="" data-function="f" data-file="a.php"',
            (new StatementRow())->attributes($entry),
        );
    }

    public function testSqlDoesNotDressUpACallNoStatementWasReadFrom(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Unknown, TextPattern::fromHole(new TextHole(Origin::Unreached, TypeShape::unknown(), '$db->query($sql)')), [], [], new CallSite('a.php', 4, 'f', 'pdo.query'), []);

        self::assertSame(
            '<span class="tok-com">no statement was read from this call</span> $db-&gt;query($sql)',
            (new StatementRow())->sql($entry),
        );
    }

    public function testMetaOmitsWhatTheListingAlreadyStatesAndShowsWhatIsNotOrdinary(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Select, TextPattern::fromHole(new TextHole(Origin::External, TypeShape::unknown())), ['users'], [], new CallSite('a.php', 4, '{main}', 'pdo.query'), [
            Finding::of(FindingRule::ExternalInput, 'x'),
        ]);
        $site = new ReportSite(new Catalog([$entry]));

        self::assertSame(
            '<span class="chip tone-danger" title="The values were followed to runtime input, so the text cannot be fixed.">external-input</span><span class="chip '
                . 'tone-danger" title="The most serious finding on this statement">high</span>',
            (new StatementRow())->meta($site, '', $entry, ['file', 'function', 'tables']),
        );
    }

    public function testAttributesNameARuleOnceHoweverOftenItWasReported(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 4, 'f', 'pdo.query'), [
            Finding::of(FindingRule::DynamicSql, 'x'),
            Finding::of(FindingRule::DynamicSql, 'y'),
        ]);

        self::assertStringContainsString(' data-rule="dynamic-sql" ', (new StatementRow())->attributes($entry));
    }

    public function testMetaNamesTheFunctionUnlessTheListingAlreadyDoes(): void
    {
        $entry = new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 4, 'f', 'pdo.query'), [
            Finding::of(FindingRule::AnalysisIncomplete, 'x'),
        ]);
        $site = new ReportSite(new Catalog([$entry]));
        $row = new StatementRow();

        self::assertSame('<a href="files/a-php.html#fn-f">f</a>', $row->meta($site, '', $entry, ['file', 'tables']));
        self::assertSame('', $row->meta($site, '', $entry, ['file', 'tables', 'function']));
    }
}
