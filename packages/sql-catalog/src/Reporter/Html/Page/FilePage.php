<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html\Page;

use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\ReportSite;
use SqlCatalog\Reporter\Html\Scope;
use SqlCatalog\Reporter\Html\StatementList;
use SqlCatalog\Reporter\Html\TableName;

/**
 * One file: the statements each function in it issues.
 *
 * A file is read top to bottom, so its statements are listed under the
 * functions that issue them in the order the functions are written, with
 * top-level code first. A method's section says which class it belongs to
 * and leads to that class's page.
 *
 * @visibility root
 */
final class FilePage
{
    private HtmlText $text;

    private StatementList $list;

    /**
     * Wires the page to the rendering it is written with.
     */
    public function __construct(?HtmlText $text = null, ?StatementList $list = null)
    {
        $this->text = $text ?? new HtmlText();
        $this->list = $list ?? new StatementList($this->text);
    }

    /**
     * The body of one file's page.
     */
    public function render(ReportSite $site, string $file): string
    {
        $entries = $site->index()->byFile()[$file] ?? [];
        $functions = $this->functions($entries);

        return '<h1><code>' . $this->text->escape($file) . '</code>' . $this->text->count(count($entries), 'statement') . '</h1>'
            . '<p class="lede">' . $this->text->escape($this->text->plural(count($entries), 'statement')) . ' issued from '
            . $this->text->escape($this->text->plural(count($functions), 'function')) . ' in this file.</p>'
            . $this->tables($site, $entries)
            . '<h2 id="functions">Functions</h2>'
            . '<div data-narrowable>' . $this->list->facets($entries) . $this->sections($site, $file, $functions) . '</div>';
    }

    /**
     * The tables a set of statements names, most named first.
     *
     * @param list<CatalogEntry> $entries
     */
    public function tables(ReportSite $site, array $entries): string
    {
        $chips = '';
        foreach ($site->index()->tablesOf($entries) as $table => $count) {
            $chips .= $this->text->chipCount((new TableName($table))->label(), '../' . $site->tablePage($table), $count, 'chip-ghost');
        }

        return '<h2 id="tables">Tables</h2>'
            . ($chips === '' ? '<p class="empty-inline">No statement here names a table.</p>' : '<div class="chips">' . $chips . '</div>');
    }

    /**
     * The statements of each function among a set, in the order the functions are written.
     *
     * @param list<CatalogEntry> $entries
     * @return array<string, list<CatalogEntry>>
     */
    public function functions(array $entries): array
    {
        $grouped = [];
        foreach ($entries as $entry) {
            $grouped[$entry->site->function][] = $entry;
        }
        uasort($grouped, static fn (array $left, array $right): int => $left[0]->site->line <=> $right[0]->site->line);

        return $grouped;
    }

    /**
     * One section per function, each listing what it issues.
     *
     * @param array<string, list<CatalogEntry>> $functions
     */
    public function sections(ReportSite $site, string $file, array $functions): string
    {
        $sections = '';
        foreach ($functions as $function => $entries) {
            $scope = Scope::of($function);
            $anchor = $site->functionAnchor($function);
            $heading = $scope->class === null
                ? '<code>' . $this->text->escape($scope->display()) . '</code>'
                : $this->text->link($scope->display(), '../' . $site->classPage($scope->class), 'mono');
            $sections .= '<section class="group" id="' . $this->text->escape($anchor) . '"><h3>' . $heading . $this->text->count(count($entries))
                . '<span class="muted">line ' . $entries[0]->site->line . '</span>'
                . '<a class="anchor" href="#' . $this->text->escape($anchor) . '">#</a></h3>'
                . $this->list->rows($site, $site->filePage($file), $entries, ['function', 'file']) . '</section>';
        }

        return $sections;
    }

    /**
     * What the page is best left from: its functions, and the other files of the directory.
     *
     * @return list<array{string, list<array{string, string, int|null, bool}>, string|null}>
     */
    public function context(ReportSite $site, string $file): array
    {
        $index = $site->index();
        $anchors = [];
        foreach ($this->anchors($site, $index->byFile()[$file] ?? []) as [$label, $id]) {
            $anchors[] = [$label, '#' . $id, null, false];
        }
        $slash = strrpos($file, '/');
        $directory = $slash === false ? '' : substr($file, 0, $slash);
        $siblings = [];
        foreach ($index->byDirectory()[$directory] ?? [] as $other) {
            $otherSlash = strrpos($other, '/');
            $siblings[] = [$otherSlash === false ? $other : substr($other, $otherSlash + 1), $site->filePage($other), count($index->byFile()[$other] ?? []), $other === $file];
        }

        return [['On this page', $anchors, null], ['Files in ' . ($directory === '' ? '(root)' : $directory . '/'), $siblings, ReportSite::FILES]];
    }

    /**
     * The sections of the page, for the navigation.
     *
     * @param list<CatalogEntry> $entries
     * @return list<array{string, string}>
     */
    public function anchors(ReportSite $site, array $entries): array
    {
        $anchors = [['Tables', 'tables']];
        foreach (array_keys($this->functions($entries)) as $function) {
            $anchors[] = [Scope::of($function)->display(), $site->functionAnchor($function)];
        }

        return $anchors;
    }
}
