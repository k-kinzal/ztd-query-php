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
 * One class: the statements each of its methods issues.
 *
 * This is the page opened before a class is refactored. The statements are
 * listed under the method that issues them, in the order the methods are
 * written, so a reader can see the shape of what each method does to the
 * database before changing how it does it.
 *
 * @visibility root
 */
final class ClassPage
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
     * The body of one class's page.
     */
    public function render(ReportSite $site, string $class): string
    {
        $index = $site->index();
        $entries = $index->byClass()[$class] ?? [];
        $methods = $this->methods($entries);
        $scope = Scope::of($class . '::x');
        $files = [];
        foreach ($entries as $entry) {
            $files[$entry->site->file] = $this->text->link($entry->site->file, '../' . $site->filePage($entry->site->file));
        }

        return '<h1><code>' . $this->text->escape($scope->classShort() ?? $class) . '</code>' . $this->text->count(count($entries), 'statement') . '</h1>'
            . '<p class="lede">' . $this->text->escape($this->text->plural(count($entries), 'statement')) . ' in '
            . $this->text->escape($this->text->plural(count($methods), 'method')) . ' of <code>' . $this->text->escape($class) . '</code>, written in '
            . implode(', ', $files) . '.</p>'
            . $this->tables($site, $entries)
            . '<h2 id="methods">Methods</h2>'
            . '<div data-narrowable>' . $this->list->facets($entries) . $this->sections($site, $site->classPage($class), $methods) . '</div>';
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
    public function methods(array $entries): array
    {
        $grouped = [];
        foreach ($entries as $entry) {
            $grouped[$entry->site->function][] = $entry;
        }
        uasort($grouped, static fn (array $left, array $right): int
            => [$left[0]->site->file, $left[0]->site->line] <=> [$right[0]->site->file, $right[0]->site->line]);

        return $grouped;
    }

    /**
     * One section per function, each listing what it issues.
     *
     * @param array<string, list<CatalogEntry>> $functions
     */
    public function sections(ReportSite $site, string $page, array $functions): string
    {
        $sections = '';
        foreach ($functions as $function => $entries) {
            $anchor = $site->functionAnchor($function);
            $sections .= '<section class="group" id="' . $this->text->escape($anchor) . '"><h3><code>'
                . $this->text->escape(Scope::of($function)->display()) . '</code>' . $this->text->count(count($entries))
                . '<span class="muted">' . $this->text->escape($entries[0]->site->display()) . '</span>'
                . '<a class="anchor" href="#' . $this->text->escape($anchor) . '">#</a></h3>'
                . $this->list->rows($site, $page, $entries, ['function']) . '</section>';
        }

        return $sections;
    }

    /**
     * What the page is best left from: its methods, and the other classes of the namespace.
     *
     * @return list<array{string, list<array{string, string, int|null, bool}>, string|null}>
     */
    public function context(ReportSite $site, string $class): array
    {
        $index = $site->index();
        $scope = Scope::of($class . '::x');
        $anchors = [];
        foreach ($this->anchors($site, $index->byClass()[$class] ?? []) as [$label, $id]) {
            $anchors[] = [$label, '#' . $id, null, false];
        }
        $siblings = [];
        foreach ($index->byClass() as $other => $entries) {
            $otherScope = Scope::of($other . '::x');
            if ($otherScope->namespace === $scope->namespace) {
                $siblings[] = [$otherScope->classShort() ?? $other, $site->classPage($other), count($entries), $other === $class];
            }
        }

        return [['On this page', $anchors, null], ['Classes in ' . $scope->namespaceLabel(), $siblings, ReportSite::NAMESPACES]];
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
        foreach (array_keys($this->methods($entries)) as $function) {
            $anchors[] = [Scope::of($function)->member, $site->functionAnchor($function)];
        }

        return $anchors;
    }
}
