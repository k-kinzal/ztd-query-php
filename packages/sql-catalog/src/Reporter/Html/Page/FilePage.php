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
            . '<div class="filterable" data-narrowable>' . $this->list->facets($entries) . $this->sections($site, $file, $functions) . '</div>';
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
            $chips .= '<li>' . $this->text->chipLink((new TableName($table))->label(), '../' . $site->tablePage($table), 'chip-ghost')
                . '<span class="route-figures">' . $this->text->number($count) . '</span></li>';
        }

        return '<h2 id="tables">Tables</h2>'
            . ($chips === '' ? '<p class="none">No statement here names a table.</p>' : '<ol class="route-top route-chips">' . $chips . '</ol>');
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
