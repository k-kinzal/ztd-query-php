<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html;

/**
 * The document every page of the report is written into.
 *
 * The page is a doc-ui document: the compact layout document-design keeps
 * for catalogs and references, with the navigation beside the reading and
 * the tools along the top. The navigation names the routes to a statement —
 * by table, by namespace, by file, by finding, or through the whole listing —
 * and then whatever the page being read is best left from: the other tables
 * beside a table, the other classes of a namespace beside a class, the places
 * a statement belongs to beside the statement, and the sections of a long
 * page. Each page says what that is; the shell only writes it.
 *
 * @visibility root
 */
final class PageShell
{
    /**
     * The release of document-design the pages are written for, and never a later one.
     */
    public const DESIGN_VERSION = 'v1.0.0';

    /**
     * The name the document-design stylesheet is written under.
     */
    public const DESIGN_STYLE = 'assets/document-design-' . self::DESIGN_VERSION . '.css';

    /**
     * The name the document-design script is written under.
     */
    public const DESIGN_SCRIPT = 'assets/document-design-' . self::DESIGN_VERSION . '.js';

    /**
     * The name the document-design license and provenance notice is written under.
     */
    public const DESIGN_LICENSE = 'assets/document-design-LICENSE.txt';

    /**
     * The name the report's own stylesheet is written under.
     */
    public const STYLE = 'assets/report.css';

    /**
     * The name the report's own script is written under.
     */
    public const SCRIPT = 'assets/report.js';

    /**
     * The name the search index is written under.
     */
    public const INDEX = 'assets/search-index.js';

    /**
     * The key the chosen theme is remembered under.
     */
    public const THEME_KEY = 'sql-catalog-theme';

    /**
     * How many entries a navigation block lists before pointing at the listing.
     */
    public const LIMIT = 40;

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
     * @param list<array{string, list<array{string, string, int|null, bool}>, string|null}> $blocks The navigation the page is best left from: a title, its entries as label, address, count and whether it is the page being read, and where the rest of them are
     */
    public function render(ReportSite $site, string $page, string $title, array $crumbs, string $body, array $blocks = []): string
    {
        $prefix = $site->prefixOf($page);

        return $this->head($prefix, $title)
            . '<body data-root="' . $this->text->escape($prefix) . '">' . "\n"
            . '<a class="skip" href="#content">Skip to content</a>' . "\n"
            . '<div class="doc">' . "\n"
            . '<nav class="sidebar" id="navigation" aria-label="Report navigation">' . $this->sidebar($site, $page, $blocks) . '</nav>' . "\n"
            . '<div class="main">' . "\n"
            . $this->topbar($crumbs, $prefix)
            . '<div class="search-results" data-dd-search-results hidden></div>' . "\n"
            . '<main class="content" id="content">' . "\n" . $body . '</main>' . "\n"
            . '<footer class="doc-footer">Written by <a href="https://github.com/k-kinzal/ztd-query-php/tree/main/packages/sql-catalog">sql-catalog</a>.</footer>' . "\n"
            . '</div>' . "\n"
            . '</div>' . "\n"
            . '<script src="' . $this->text->escape($prefix . self::INDEX) . '" defer></script>' . "\n"
            . '<script src="' . $this->text->escape($prefix . self::SCRIPT) . '" defer></script>' . "\n"
            . '<script src="' . $this->text->escape($prefix . self::DESIGN_SCRIPT) . '" defer></script>' . "\n"
            . '</body>' . "\n"
            . '</html>' . "\n";
    }

    /**
     * The head of a page: its title, the two stylesheets, and the theme restored before the first paint.
     *
     * The document-design stylesheet comes first and the report's own after
     * it, so what the report adds is read on top of the design and not under
     * it.
     */
    public function head(string $prefix, string $title): string
    {
        return '<!DOCTYPE html>' . "\n"
            . '<html lang="en" data-dd-theme-key="' . self::THEME_KEY . '">' . "\n"
            . '<head>' . "\n"
            . '<meta charset="utf-8">' . "\n"
            . '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n"
            . '<meta name="color-scheme" content="light dark">' . "\n"
            . '<title>' . $this->text->escape($title) . '</title>' . "\n"
            . '<link rel="stylesheet" href="' . $this->text->escape($prefix . self::DESIGN_STYLE) . '">' . "\n"
            . '<link rel="stylesheet" href="' . $this->text->escape($prefix . self::STYLE) . '">' . "\n"
            . $this->bootstrap() . "\n"
            . '</head>' . "\n";
    }

    /**
     * The bar along the top of a page: the way into the navigation on a phone, the trail, the search and the theme.
     *
     * The search box and the theme switch are hidden until the script that
     * drives them has run, so a page read without it shows no control that
     * does nothing.
     *
     * @param list<array{string, string|null}> $crumbs
     */
    public function topbar(array $crumbs, string $prefix): string
    {
        return '<header class="topbar">' . "\n"
            . '<button class="btn nav-toggle" type="button" data-dd-nav-toggle aria-controls="navigation" aria-expanded="false" aria-label="Open navigation">☰</button>' . "\n"
            . '<nav class="breadcrumbs" aria-label="Breadcrumb">' . $this->crumbs($crumbs, $prefix) . '</nav>' . "\n"
            . '<div class="topbar-tools">' . "\n"
            . '<input type="search" id="search" class="input input-search" data-dd-search data-dd-enhance hidden placeholder="Find a statement… ( / )" aria-label="Search by SQL text, table, function or file" autocomplete="off" spellcheck="false">' . "\n"
            . '<button class="btn" type="button" data-dd-theme-toggle data-dd-enhance hidden aria-label="Switch theme">◐</button>' . "\n"
            . '</div>' . "\n"
            . '</header>' . "\n";
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
                ? '<span class="breadcrumb-current">' . $this->text->escape($label) . '</span>'
                : '<a href="' . $this->text->escape($prefix . $href) . '">' . $this->text->escape($label) . '</a>';
        }

        return implode('<span class="breadcrumb-sep">/</span>', $written);
    }

    /**
     * The navigation shown beside a page: the routes, then what the page itself is best left from.
     *
     * @param list<array{string, list<array{string, string, int|null, bool}>, string|null}> $blocks
     */
    public function sidebar(ReportSite $site, string $page, array $blocks): string
    {
        $prefix = $site->prefixOf($page);
        $written = $this->routes($site, $page);
        foreach ($blocks as [$title, $items, $rest]) {
            $written .= $this->block($title, $items, $rest, $prefix);
        }

        return $written;
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
                . '<a href="' . $this->text->escape($prefix . $target) . '"' . ($active ? ' aria-current="page"' : '') . '>' . $label . '</a>'
                . ($count === null ? '' : '<span class="sidebar-count">' . $this->text->number($count) . '</span>') . '</li>';
        }

        return '<div class="sidebar-section"><p class="sidebar-title">Browse</p><ul class="sidebar-list">' . $items . '</ul></div>';
    }

    /**
     * One block of navigation: a title and the entries under it.
     *
     * An address starting with `#` is a section of the page being read and is
     * written as it is; any other is relative to the root of the report. A
     * block of nothing but sections is the table of contents, and is marked so
     * the section being read is followed as the page scrolls. A block longer
     * than the limit is cut, and says where the rest are.
     *
     * @param list<array{string, string, int|null, bool}> $items
     * @param string|null $rest Where every entry is listed, for a block that had to be cut
     */
    public function block(string $title, array $items, ?string $rest, string $prefix): string
    {
        if ($items === []) {
            return '';
        }
        $written = '';
        $sections = true;
        foreach (array_slice($items, 0, self::LIMIT) as [$label, $href, $count, $active]) {
            $section = str_starts_with($href, '#');
            $sections = $sections && $section;
            $written .= '<li' . ($active ? ' class="is-active"' : '') . '>'
                . '<a href="' . $this->text->escape($section ? $href : $prefix . $href) . '" title="' . $this->text->escape($label) . '"' . ($active ? ' aria-current="page"' : '') . '>'
                . $this->text->escape($label) . '</a>'
                . ($count === null ? '' : '<span class="sidebar-count">' . $this->text->number($count) . '</span>') . '</li>';
        }
        if (count($items) > self::LIMIT && $rest !== null) {
            $written .= '<li class="sidebar-more"><a href="' . $this->text->escape($prefix . $rest) . '">All ' . $this->text->number(count($items)) . '…</a></li>';
        }

        return '<div class="sidebar-section"><p class="sidebar-title">' . $this->text->escape($title) . '</p>'
            . '<ul class="sidebar-list sidebar-context"' . ($sections ? ' data-dd-toc' : '') . '>' . $written . '</ul></div>';
    }

    /**
     * The sections of the page being read, as a block.
     *
     * @param list<array{string, string}> $anchors
     * @return list<array{string, list<array{string, string, int|null, bool}>, string|null}>
     */
    public function onThisPage(array $anchors): array
    {
        if ($anchors === []) {
            return [];
        }
        $items = [];
        foreach ($anchors as [$label, $id]) {
            $items[] = [$label, '#' . $id, null, false];
        }

        return [['On this page', $items, null]];
    }

    /**
     * The inline script that restores the chosen theme before the page is laid out.
     *
     * The theme is kept where document-design reads it, so the switch in the
     * topbar and this script agree on what the reader chose.
     */
    public function bootstrap(): string
    {
        return '<script>try{var t=localStorage.getItem("' . self::THEME_KEY . '");if(t){document.documentElement.dataset.ddTheme=t}}catch(e){}</script>';
    }
}
