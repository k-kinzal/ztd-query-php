<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html;

/**
 * The document every page of the report is written into.
 *
 * The navigation names the routes to a statement — by table, by namespace, by
 * file, by finding, or through the whole listing — and nothing else, so it
 * costs the same on a report of fifty statements and on one of five thousand.
 * What is on the page being read is listed beside it, so a long page can be
 * jumped through rather than scrolled.
 *
 * @visibility root
 */
final class PageShell
{
    /**
     * The name the stylesheet is written under.
     */
    public const STYLE = 'assets/report.css';

    /**
     * The name the client script is written under.
     */
    public const SCRIPT = 'assets/report.js';

    /**
     * The name the search index is written under.
     */
    public const INDEX = 'assets/search-index.js';

    private HtmlText $text;

    /**
     * Wires the shell to the escaping it writes through.
     */
    public function __construct(?HtmlText $text = null)
    {
        $this->text = $text ?? new HtmlText();
    }

    /**
     * One complete page of the report.
     *
     * @param string $page The name the page is written under, which fixes what its links are relative to
     * @param list<array{string, string|null}> $crumbs The trail to this page, as label and address pairs
     * @param list<array{string, string}> $anchors The sections of the page, as label and identifier pairs
     */
    public function render(ReportSite $site, string $page, string $title, array $crumbs, string $body, array $anchors = []): string
    {
        $prefix = $site->prefixOf($page);

        return '<!DOCTYPE html>' . "\n"
            . '<html lang="en">' . "\n"
            . '<head>' . "\n"
            . '<meta charset="utf-8">' . "\n"
            . '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n"
            . '<title>' . $this->text->escape($title) . ' — SQL catalog</title>' . "\n"
            . '<link rel="stylesheet" href="' . $this->text->escape($prefix . self::STYLE) . '">' . "\n"
            . $this->bootstrap() . "\n"
            . '</head>' . "\n"
            . '<body data-root="' . $this->text->escape($prefix) . '">' . "\n"
            . '<nav class="sidebar" id="sidebar">' . $this->sidebar($site, $page, $anchors) . '</nav>' . "\n"
            . '<div class="page">' . "\n"
            . '<header class="topbar">' . "\n"
            . '<button class="nav-toggle" id="nav-toggle" title="Toggle navigation">☰</button>' . "\n"
            . '<nav class="crumbs">' . $this->crumbs($crumbs, $prefix) . '</nav>' . "\n"
            . '<div class="topbar-tools">' . "\n"
            . '<input type="search" id="search" placeholder="Find a statement… ( / )" title="Search by SQL text, table, function or file" autocomplete="off" spellcheck="false">' . "\n"
            . '<button id="theme-toggle" title="Toggle theme">◐</button>' . "\n"
            . '</div>' . "\n"
            . '</header>' . "\n"
            . '<div class="search-results" id="search-results" hidden></div>' . "\n"
            . '<main class="content">' . "\n" . $body . '</main>' . "\n"
            . '</div>' . "\n"
            . '<script src="' . $this->text->escape($prefix . self::INDEX) . '" defer></script>' . "\n"
            . '<script src="' . $this->text->escape($prefix . self::SCRIPT) . '" defer></script>' . "\n"
            . '</body>' . "\n"
            . '</html>' . "\n";
    }

    /**
     * The trail shown along the top of a page.
     *
     * @param list<array{string, string|null}> $crumbs
     */
    public function crumbs(array $crumbs, string $prefix): string
    {
        $written = [];
        foreach ($crumbs as [$label, $href]) {
            $written[] = $href === null
                ? '<span class="crumb-current">' . $this->text->escape($label) . '</span>'
                : '<a href="' . $this->text->escape($prefix . $href) . '">' . $this->text->escape($label) . '</a>';
        }

        return implode('<span class="crumb-sep">/</span>', $written);
    }

    /**
     * The navigation shown beside every page.
     *
     * @param list<array{string, string}> $anchors
     */
    public function sidebar(ReportSite $site, string $page, array $anchors): string
    {
        $prefix = $site->prefixOf($page);

        return '<div class="sb-head">'
            . '<a class="sb-site" href="' . $this->text->escape($prefix . ReportSite::INDEX) . '">SQL catalog</a>'
            . '<span class="sb-root">' . $this->text->escape($this->text->plural($site->statistics()->statements(), 'statement')) . '</span>'
            . '</div>'
            . $this->routes($site, $page)
            . $this->anchors($anchors);
    }

    /**
     * The routes a reader can take to a statement, with what each one holds.
     */
    public function routes(ReportSite $site, string $page): string
    {
        $routes = [
            [ReportSite::INDEX, 'Overview', null],
            [ReportSite::STATEMENTS, 'Statements', $site->statistics()->statements()],
            [ReportSite::TABLES, 'Tables', count($site->tables())],
            [ReportSite::NAMESPACES, 'Namespaces', count($site->index()->byNamespace())],
            [ReportSite::FILES, 'Files', count($site->files())],
            [ReportSite::FINDINGS, 'Findings', $site->statistics()->findings()],
        ];
        $prefix = $site->prefixOf($page);
        $items = '';
        foreach ($routes as [$target, $label, $count]) {
            $directory = substr($target, 0, -5) . '/';
            $active = $page === $target || str_starts_with($page, $directory);
            $items .= '<li' . ($active ? ' class="is-active"' : '') . '>'
                . '<a href="' . $this->text->escape($prefix . $target) . '">' . $label . '</a>'
                . ($count === null ? '' : '<span class="sb-count">' . $this->text->number($count) . '</span>') . '</li>';
        }

        return '<div class="sb-block"><p class="sb-title">Browse</p><ul class="sb-list">' . $items . '</ul></div>';
    }

    /**
     * The sections of the page being read.
     *
     * @param list<array{string, string}> $anchors
     */
    public function anchors(array $anchors): string
    {
        if ($anchors === []) {
            return '';
        }
        $items = '';
        foreach ($anchors as [$label, $id]) {
            $items .= '<li><a href="#' . $this->text->escape($id) . '" title="' . $this->text->escape($label) . '">'
                . $this->text->escape($label) . '</a></li>';
        }

        return '<div class="sb-block"><p class="sb-title">On this page</p><ul class="sb-list sb-anchors">' . $items . '</ul></div>';
    }

    /**
     * The inline script that restores the chosen theme before the page is laid out.
     */
    public function bootstrap(): string
    {
        return '<script>try{var t=localStorage.getItem("sql-catalog-theme");if(t){document.documentElement.dataset.theme=t}}catch(e){}</script>';
    }
}
