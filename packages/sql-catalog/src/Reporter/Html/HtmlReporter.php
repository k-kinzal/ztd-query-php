<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html;

use Override;
use SqlCatalog\Core\Catalog\Catalog;
use SqlCatalog\Core\Reporter\CatalogArtifacts;
use SqlCatalog\Core\Reporter\ReporterInterface;
use SqlCatalog\Reporter\Html\Page\ClassPage;
use SqlCatalog\Reporter\Html\Page\FileIndexPage;
use SqlCatalog\Reporter\Html\Page\FilePage;
use SqlCatalog\Reporter\Html\Page\FindingPage;
use SqlCatalog\Reporter\Html\Page\NamespacePage;
use SqlCatalog\Reporter\Html\Page\OverviewPage;
use SqlCatalog\Reporter\Html\Page\StatementIndexPage;
use SqlCatalog\Reporter\Html\Page\StatementPage;
use SqlCatalog\Reporter\Html\Page\TableIndexPage;
use SqlCatalog\Reporter\Html\Page\TablePage;

/**
 * Writes the catalog as a site of linked HTML pages.
 *
 * A reader comes to a catalog to find a statement and decide something about
 * it, so the site is laid out as the routes to one: by the table it names, by
 * the namespace and class that issue it, by the file it is written in, by
 * what the analysis reported on it, or through the whole listing narrowed
 * down. Every statement has a page of its own, which is where every route
 * ends.
 *
 * @visibility root
 */
class HtmlReporter implements ReporterInterface
{
    /**
     * Supplies SQL presentation independently of page rendering.
     */
    public function __construct(private readonly SqlFormatter $formatter = new SqlFormatter())
    {
    }

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
        $site = new ReportSite($catalog, formatter: $this->formatter);
        $shell = new PageShell();
        $home = ['Overview', ReportSite::INDEX];

        $files = (new ReportAssets())->all();
        $files[PageShell::INDEX] = (new SearchIndex())->render($site);
        $files[ReportSite::INDEX] = $shell->render($site, ReportSite::INDEX, 'Overview', [['Overview', null]], (new OverviewPage())->render($site), $shell->onThisPage([
            ['Needs attention', 'attention'],
            ['How far the analysis got', 'coverage'],
        ]));
        $files[ReportSite::STATEMENTS] = $shell->render($site, ReportSite::STATEMENTS, 'Statements', [$home, ['Statements', null]], (new StatementIndexPage())->render($site));
        $files[ReportSite::TABLES] = $shell->render($site, ReportSite::TABLES, 'Tables', [$home, ['Tables', null]], (new TableIndexPage())->render($site));
        $namespaces = new NamespacePage();
        $files[ReportSite::NAMESPACES] = $shell->render($site, ReportSite::NAMESPACES, 'Namespaces', [$home, ['Namespaces', null]], $namespaces->render($site), $shell->onThisPage($namespaces->anchors($site)));
        $directories = new FileIndexPage();
        $files[ReportSite::FILES] = $shell->render($site, ReportSite::FILES, 'Files', [$home, ['Files', null]], $directories->render($site), $shell->onThisPage($directories->anchors($site)));
        $findings = new FindingPage();
        $files[ReportSite::FINDINGS] = $shell->render($site, ReportSite::FINDINGS, 'Findings', [$home, ['Findings', null]], $findings->render($site), $shell->onThisPage($findings->anchors($site)));

        return new CatalogArtifacts(
            array_merge($files, $this->tablePages($site, $shell), $this->classPages($site, $shell), $this->filePages($site, $shell), $this->statementPages($site, $shell)),
            ReportSite::INDEX,
        );
    }

    /**
     * One page per table the catalog names.
     *
     * @return array<string, string>
     */
    public function tablePages(ReportSite $site, PageShell $shell): array
    {
        $page = new TablePage();
        $pages = [];
        foreach ($site->tables() as $table) {
            $name = $site->tablePage($table);
            $label = (new TableName($table))->label();
            $pages[$name] = $shell->render(
                $site,
                $name,
                $label,
                [['Overview', ReportSite::INDEX], ['Tables', ReportSite::TABLES], [$label, null]],
                $page->render($site, $table),
                $page->context($site, $table),
            );
        }

        return $pages;
    }

    /**
     * One page per class that issues a statement.
     *
     * @return array<string, string>
     */
    public function classPages(ReportSite $site, PageShell $shell): array
    {
        $page = new ClassPage();
        $pages = [];
        foreach ($site->classes() as $class) {
            $name = $site->classPage($class);
            $pages[$name] = $shell->render(
                $site,
                $name,
                $class,
                [['Overview', ReportSite::INDEX], ['Namespaces', ReportSite::NAMESPACES], [Scope::of($class . '::x')->classShort() ?? $class, null]],
                $page->render($site, $class),
                $page->context($site, $class),
            );
        }

        return $pages;
    }

    /**
     * One page per file a statement is written in.
     *
     * @return array<string, string>
     */
    public function filePages(ReportSite $site, PageShell $shell): array
    {
        $page = new FilePage();
        $pages = [];
        foreach ($site->files() as $file) {
            $name = $site->filePage($file);
            $pages[$name] = $shell->render(
                $site,
                $name,
                $file,
                [['Overview', ReportSite::INDEX], ['Files', ReportSite::FILES], [$file, null]],
                $page->render($site, $file),
                $page->context($site, $file),
            );
        }

        return $pages;
    }

    /**
     * One page per statement.
     *
     * @return array<string, string>
     */
    public function statementPages(ReportSite $site, PageShell $shell): array
    {
        $page = new StatementPage();
        $pages = [];
        foreach ($site->index()->entries() as $entry) {
            $name = $site->statementPage($entry->id);
            $pages[$name] = $shell->render(
                $site,
                $name,
                strtoupper($entry->kind->value) . ' at ' . $entry->site->display(),
                [['Overview', ReportSite::INDEX], ['Statements', ReportSite::STATEMENTS], [$entry->id, null]],
                $page->render($site, $entry),
                $page->context($site, $entry),
            );
        }

        return $pages;
    }
}
