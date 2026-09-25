<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Reporter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Catalog\Catalog;
use SqlCatalog\Core\Reporter\CatalogArtifacts;
use SqlCatalog\Core\Reporter\ReporterInterface;
use SqlCatalog\Facade\HtmlReporter;
use SqlCatalog\Reporter\Json\JsonReporter;
use SqlCatalog\Reporter\Text\TextReporter;

#[CoversClass(ReporterInterface::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(CatalogArtifacts::class)]
#[UsesClass(HtmlReporter::class)]
#[UsesClass(JsonReporter::class)]
#[UsesClass(TextReporter::class)]
#[UsesClass(\SqlCatalog\Core\Catalog\Resolution::class)]
#[UsesClass(\SqlCatalog\Reporter\Html\CatalogStatistics::class)]
#[UsesClass(\SqlCatalog\Reporter\Html\HtmlText::class)]
#[UsesClass(\SqlCatalog\Reporter\Html\CatalogIndex::class)]
#[UsesClass(\SqlCatalog\Reporter\Html\Palette::class)]
#[UsesClass(\SqlCatalog\Reporter\Html\Scope::class)]
#[UsesClass(\SqlCatalog\Reporter\Html\SqlFormatter::class)]
#[UsesClass(\SqlCatalog\Reporter\Html\StatementList::class)]
#[UsesClass(\SqlCatalog\Reporter\Html\StatementRow::class)]
#[UsesClass(\SqlCatalog\Reporter\Html\TableName::class)]
#[UsesClass(\SqlCatalog\Reporter\Html\Page\ClassPage::class)]
#[UsesClass(\SqlCatalog\Reporter\Html\Page\FileIndexPage::class)]
#[UsesClass(\SqlCatalog\Reporter\Html\Page\FilePage::class)]
#[UsesClass(\SqlCatalog\Reporter\Html\Page\FindingPage::class)]
#[UsesClass(\SqlCatalog\Reporter\Html\Page\NamespacePage::class)]
#[UsesClass(\SqlCatalog\Reporter\Html\Page\OverviewPage::class)]
#[UsesClass(\SqlCatalog\Reporter\Html\Page\StatementIndexPage::class)]
#[UsesClass(\SqlCatalog\Reporter\Html\Page\StatementPage::class)]
#[UsesClass(\SqlCatalog\Reporter\Html\Page\TableIndexPage::class)]
#[UsesClass(\SqlCatalog\Reporter\Html\Page\TablePage::class)]
#[UsesClass(\SqlCatalog\Reporter\Html\PageShell::class)]
#[UsesClass(\SqlCatalog\Reporter\Html\ReportAssets::class)]
#[UsesClass(\SqlCatalog\Reporter\Html\ReportSite::class)]
#[UsesClass(\SqlCatalog\Reporter\Html\SearchIndex::class)]
#[UsesClass(\SqlCatalog\Reporter\Html\SqlHighlighter::class)]
final class ReporterInterfaceTest extends TestCase
{
    public function testNameIsUniqueAcrossTheReportersThatShip(): void
    {
        $names = array_map(
            static fn (ReporterInterface $reporter): string => $reporter->name(),
            [new JsonReporter(), new HtmlReporter(), new TextReporter()],
        );
        self::assertSame($names, array_values(array_unique($names)));
    }

    public function testDescriptionIsAlwaysWritten(): void
    {
        $descriptions = array_map(
            static fn (ReporterInterface $reporter): string => $reporter->description(),
            [new JsonReporter(), new HtmlReporter(), new TextReporter()],
        );
        self::assertNotContains('', $descriptions);
    }

    public function testRenderAlwaysProducesSomethingToShow(): void
    {
        $primary = array_map(
            static fn (ReporterInterface $reporter): bool => $reporter->render(new Catalog())->primary() !== null,
            [new JsonReporter(), new HtmlReporter(), new TextReporter()],
        );

        self::assertSame([true, true, true], $primary);
    }
}
