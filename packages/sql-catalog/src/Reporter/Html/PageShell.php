<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html;

/**
 * The document every page of the report is written into.
 *
 * The navigation is sized so that it costs the same on a report of fifty
 * statements and on one of five thousand: the pages are listed as numbers, the
 * files of the page being read are listed by name, and the full index of files
 * lives on the overview rather than in the margin of every page.
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
     */
    public function render(ReportSite $site, string $page, string $title, array $crumbs, string $body): string
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
            . '<nav class="sidebar" id="sidebar">' . $this->sidebar($site, $page) . '</nav>' . "\n"
            . '<div class="page">' . "\n"
            . '<header class="topbar">' . "\n"
            . '<button class="nav-toggle" id="nav-toggle" title="Toggle navigation">☰</button>' . "\n"
            . '<nav class="crumbs">' . $this->crumbs($crumbs, $prefix) . '</nav>' . "\n"
            . '<div class="topbar-tools">' . "\n"
            . '<input type="search" id="search" placeholder="Search statements… ( / )" autocomplete="off" spellcheck="false">' . "\n"
            . '<button id="theme-toggle" title="Toggle theme">◐</button>' . "\n"
            . '</div>' . "\n"
            . '</header>' . "\n"
            . '<div class="search-results" id="search-results" hidden></div>' . "\n"
            . '<main class="content">' . "\n" . $body . '</main>' . "\n"
            . '<footer class="site-footer">Every statement here was read back from the call that receives it. '
            . 'A gap marked <span class="hole">{$}</span> is a value the analysis could not pin down, not a value the program leaves empty.</footer>' . "\n"
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
     */
    public function sidebar(ReportSite $site, string $page): string
    {
        $prefix = $site->prefixOf($page);

        return '<div class="sb-head">'
            . '<a class="sb-site" href="' . $this->text->escape($prefix . ReportSite::INDEX) . '">SQL catalog</a>'
            . '<span class="sb-root">' . $this->text->escape($this->text->plural(count($site->files()), 'file')) . '</span>'
            . '</div>'
            . $this->reportBlock($site, $page)
            . $this->filesBlock($site, $page)
            . $this->pagesBlock($site, $page);
    }

    /**
     * The links to the pages that stand for the whole catalog.
     */
    public function reportBlock(ReportSite $site, string $page): string
    {
        $prefix = $site->prefixOf($page);
        $items = '';
        foreach ([ReportSite::INDEX => 'Overview', ReportSite::TABLES => 'Tables', ReportSite::FINDINGS => 'Findings'] as $target => $label) {
            $items .= '<li' . ($page === $target ? ' class="is-active"' : '') . '>'
                . '<a href="' . $this->text->escape($prefix . $target) . '">' . $label . '</a></li>';
        }

        return '<div class="sb-block"><p class="sb-title">Report</p><ul class="sb-list">' . $items . '</ul></div>';
    }

    /**
     * The files listed on the page being read.
     */
    public function filesBlock(ReportSite $site, string $page): string
    {
        $number = $this->numberOf($site, $page);
        if ($number === null) {
            return '';
        }
        $items = '';
        foreach ($site->pages()[$number - 1] ?? [] as $file => $entries) {
            $items .= '<li><a href="#' . $this->text->escape($site->fileAnchor($file)) . '" title="'
                . $this->text->escape($file) . '">' . $this->text->escape($file) . '</a>'
                . '<span class="sb-count">' . $this->text->number(count($entries)) . '</span></li>';
        }

        return $items === '' ? '' : '<div class="sb-block"><p class="sb-title">On this page</p><ul class="sb-list">' . $items . '</ul></div>';
    }

    /**
     * The links to every page of statements.
     */
    public function pagesBlock(ReportSite $site, string $page): string
    {
        if ($site->pageCount() < 2) {
            return '';
        }
        $prefix = $site->prefixOf($page);
        $current = $this->numberOf($site, $page);
        $items = '';
        for ($number = 1; $number <= $site->pageCount(); $number++) {
            $items .= '<li' . ($number === $current ? ' class="is-active"' : '') . '>'
                . '<a href="' . $this->text->escape($prefix . $site->pageName($number)) . '">Page ' . $number . '</a>'
                . '<span class="sb-count">' . $this->text->number(array_sum(array_map(
                    'count',
                    $site->pages()[$number - 1] ?? [],
                ))) . '</span></li>';
        }

        return '<div class="sb-block"><p class="sb-title">Statements</p><ul class="sb-list">' . $items . '</ul></div>';
    }

    /**
     * Which page of statements is being read, or null when the page is not one.
     */
    public function numberOf(ReportSite $site, string $page): ?int
    {
        for ($number = 1; $number <= $site->pageCount(); $number++) {
            if ($site->pageName($number) === $page) {
                return $number;
            }
        }

        return null;
    }

    /**
     * The inline script that restores the chosen theme before the page is laid out.
     */
    public function bootstrap(): string
    {
        return '<script>try{var t=localStorage.getItem("sql-catalog-theme");if(t){document.documentElement.dataset.theme=t}}catch(e){}</script>';
    }
}
