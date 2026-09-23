<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html;

/**
 * The document every page of the report is written into.
 *
 * The navigation names the routes to a statement — by table, by namespace, by
 * file, by finding, or through the whole listing — and then whatever the page
 * being read is best left from: the other tables beside a table, the other
 * classes of a namespace beside a class, the places a statement belongs to
 * beside the statement, and the sections of a long page. Each page says what
 * that is; the shell only writes it.
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

        return '<!DOCTYPE html>' . "\n"
            . '<html lang="en">' . "\n"
            . '<head>' . "\n"
            . '<meta charset="utf-8">' . "\n"
            . '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n"
            . '<title>' . $this->text->escape($title) . '</title>' . "\n"
            . '<link rel="stylesheet" href="' . $this->text->escape($prefix . self::STYLE) . '">' . "\n"
            . $this->bootstrap() . "\n"
            . '</head>' . "\n"
            . '<body data-root="' . $this->text->escape($prefix) . '">' . "\n"
            . '<nav class="sidebar" id="sidebar">' . $this->sidebar($site, $page, $blocks) . '</nav>' . "\n"
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
                . '<a href="' . $this->text->escape($prefix . $target) . '">' . $label . '</a>'
                . ($count === null ? '' : '<span class="sb-count">' . $this->text->number($count) . '</span>') . '</li>';
        }

        return '<div class="sb-block"><p class="sb-title">Browse</p><ul class="sb-list">' . $items . '</ul></div>';
    }

    /**
     * One block of navigation: a title and the entries under it.
     *
     * An address starting with `#` is a section of the page being read and is
     * written as it is; any other is relative to the root of the report. A
     * block longer than the limit is cut, and says where the rest are.
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
        foreach (array_slice($items, 0, self::LIMIT) as [$label, $href, $count, $active]) {
            $written .= '<li' . ($active ? ' class="is-active"' : '') . '>'
                . '<a href="' . $this->text->escape(str_starts_with($href, '#') ? $href : $prefix . $href) . '" title="' . $this->text->escape($label) . '">'
                . $this->text->escape($label) . '</a>'
                . ($count === null ? '' : '<span class="sb-count">' . $this->text->number($count) . '</span>') . '</li>';
        }
        if (count($items) > self::LIMIT && $rest !== null) {
            $written .= '<li class="sb-more"><a href="' . $this->text->escape($prefix . $rest) . '">All ' . $this->text->number(count($items)) . '…</a></li>';
        }

        return '<div class="sb-block"><p class="sb-title">' . $this->text->escape($title) . '</p><ul class="sb-list sb-context">' . $written . '</ul></div>';
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
     */
    public function bootstrap(): string
    {
        return '<script>try{var t=localStorage.getItem("sql-catalog-theme");if(t){document.documentElement.dataset.theme=t}}catch(e){}</script>';
    }
}
