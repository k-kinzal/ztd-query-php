<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter;

use Override;
use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Reporter\Html\CatalogStatistics;
use SqlCatalog\Reporter\Html\FindingPage;
use SqlCatalog\Reporter\Html\OverviewPage;
use SqlCatalog\Reporter\Html\PageShell;
use SqlCatalog\Reporter\Html\ReportAssets;
use SqlCatalog\Reporter\Html\ReportSite;
use SqlCatalog\Reporter\Html\SearchIndex;
use SqlCatalog\Reporter\Html\StatementPage;
use SqlCatalog\Reporter\Html\TablePage;

/**
 * Writes the catalog as a site of linked HTML pages.
 *
 * A catalog of a real application is thousands of statements, which is more
 * than one document can carry. The statements are split across pages by the
 * file they are written in, and everything that stands for the whole catalog —
 * the overview, the tables, the findings — links into those pages rather than
 * repeating them. A search index written beside the pages is what keeps one
 * statement findable once it is no longer all on one screen.
 *
 * @visibility root
 */
final class HtmlReporter implements ReporterInterface
{
    /**
     * The name the page a reader opens first is written under.
     */
    public const FILE = ReportSite::INDEX;

    /**
     * The name the command line selects this reporter by.
     */
    #[Override]
    public function name(): string
    {
        return 'html';
    }

    /**
     * What the reporter produces.
     */
    #[Override]
    public function description(): string
    {
        return 'a site of linked HTML pages, for reading and sharing';
    }

    /**
     * The catalog rendered as a site of pages.
     */
    #[Override]
    public function render(Catalog $catalog): CatalogArtifacts
    {
        $site = new ReportSite($catalog);
        $stats = new CatalogStatistics($catalog);
        $shell = new PageShell();

        $files = (new ReportAssets())->all();
        $files[PageShell::INDEX] = (new SearchIndex())->render($site, $catalog);
        $files[ReportSite::INDEX] = $shell->render(
            $site,
            ReportSite::INDEX,
            'Overview',
            [['SQL catalog', null]],
            (new OverviewPage())->render($site, $catalog, $stats),
        );
        $files[ReportSite::TABLES] = $shell->render(
            $site,
            ReportSite::TABLES,
            'Tables',
            [['SQL catalog', ReportSite::INDEX], ['Tables', null]],
            (new TablePage())->render($site, $catalog, $stats),
        );
        $files[ReportSite::FINDINGS] = $shell->render(
            $site,
            ReportSite::FINDINGS,
            'Findings',
            [['SQL catalog', ReportSite::INDEX], ['Findings', null]],
            (new FindingPage())->render($site, $catalog, $stats),
        );

        return new CatalogArtifacts(array_merge($files, $this->statementPages($site, $shell)), ReportSite::INDEX);
    }

    /**
     * Every page of statements the catalog splits into.
     *
     * @return array<string, string>
     */
    public function statementPages(ReportSite $site, PageShell $shell): array
    {
        $statements = new StatementPage();
        $pages = [];
        for ($number = 1; $number <= $site->pageCount(); $number++) {
            $name = $site->pageName($number);
            $pages[$name] = $shell->render(
                $site,
                $name,
                'Statements, page ' . $number,
                [['SQL catalog', ReportSite::INDEX], ['Statements', null], ['Page ' . $number, null]],
                $statements->render($site, $number),
            );
        }

        return $pages;
    }
}
