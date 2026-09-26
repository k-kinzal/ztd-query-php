<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html\Page;

use SqlCatalog\Core\Catalog\CatalogEntry;
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\ReportSite;
use SqlCatalog\Reporter\Html\Scope;

/**
 * Every namespace, with the classes and functions under it that issue statements.
 *
 * Code is organised by namespace and class, so that is how a reader looking
 * for the SQL behind a piece of code looks for it. A class leads to its own
 * page; a function to the file it is written in, since a function has no
 * page of its own; and a namespace to the whole of what it issues.
 *
 * @visibility root
 */
final class NamespacePage
{
    private HtmlText $text;

    /**
     * Wires the page to the escaping it writes through.
     */
    public function __construct(?HtmlText $text = null)
    {
        $this->text = $text ?? new HtmlText();
    }

    /**
     * The body of the namespaces listing.
     */
    public function render(ReportSite $site): string
    {
        $namespaces = $site->index()->byNamespace();
        if ($namespaces === []) {
            return '<h1>Namespaces</h1><p class="lede">No statement was found.</p>';
        }
        $sections = '';
        foreach ($namespaces as $namespace => $entries) {
            $sections .= $this->section($site, $namespace, $entries);
        }

        return '<h1>Namespaces' . $this->text->count(count($namespaces), 'namespace') . '</h1>'
            . '<p class="lede">The classes and functions that issue statements, under the namespace each is declared in. '
            . 'Open a class to read its statements method by method.</p>'
            . '<input type="search" name="filter" class="input input-block" data-filter-rows placeholder="Narrow by class or function name…" aria-label="Narrow by class or function name" autocomplete="off" spellcheck="false">'
            . $sections;
    }

    /**
     * One namespace, with everything declared in it that issues statements.
     *
     * @param list<CatalogEntry> $entries
     */
    public function section(ReportSite $site, string $namespace, array $entries): string
    {
        $index = $site->index();
        $rows = '';
        foreach ($this->members($entries) as $member => $issued) {
            $scope = Scope::of($issued[0]->site->function);
            $isClass = $scope->class === $member;
            $usage = $index->usage($issued);
            $href = $isClass ? $site->classPage($member) : ($scope->isMain()
                ? ReportSite::STATEMENTS . '?function=' . rawurlencode($member)
                : $site->functionUrl($issued[0]));
            $rows .= '<tr><td>' . $this->text->link($isClass ? $member : $scope->display(), $href, 'mono') . '</td>'
                . '<td class="tight muted">' . ($isClass ? 'class' : ($scope->isMain() ? 'top-level code' : 'function')) . '</td>'
                . '<td>' . $this->text->link($issued[0]->site->file, $site->filePage($issued[0]->site->file), 'muted') . '</td>'
                . '<td class="num">' . $this->text->number(count($issued)) . '</td>'
                . '<td class="num">' . $this->text->number(count($index->tablesOf($issued))) . '</td>'
                . '<td class="num">' . ($usage['attention'] === 0 ? '<span class="none">0</span>' : $this->text->number($usage['attention'])) . '</td></tr>';
        }
        $label = Scope::of(($namespace === '' ? '' : $namespace . '\\') . 'x')->namespaceLabel();

        return '<section class="group"><h2 id="' . $this->text->escape('ns-' . $this->text->slug($label)) . '"><code>' . $this->text->escape($label) . '</code>'
            . $this->text->count(count($entries), 'statement')
            . $this->text->link('All statements', ReportSite::STATEMENTS . '?namespace=' . rawurlencode($namespace), 'anchor') . '</h2>'
            . '<div class="table-wrap"><table class="sortable filter-target" data-dd-sortable><thead><tr><th scope="col" data-dd-sort="text">Name</th><th scope="col" class="tight">Kind</th>'
            . '<th scope="col" data-dd-sort="text">File</th><th scope="col" class="num" data-dd-sort="number">Statements</th><th scope="col" class="num" data-dd-sort="number">Tables</th>'
            . '<th scope="col" class="num" data-dd-sort="number">Attention</th></tr></thead><tbody>' . $rows . '</tbody></table></div></section>';
    }

    /**
     * The statements under each class or free function among a set, classes first.
     *
     * @param list<CatalogEntry> $entries
     * @return array<string, list<CatalogEntry>>
     */
    public function members(array $entries): array
    {
        $classes = [];
        $functions = [];
        foreach ($entries as $entry) {
            $scope = Scope::of($entry->site->function);
            if ($scope->class !== null) {
                $classes[$scope->class][] = $entry;
            } else {
                $functions[$entry->site->function][] = $entry;
            }
        }
        ksort($classes, SORT_STRING | SORT_FLAG_CASE);
        ksort($functions, SORT_STRING | SORT_FLAG_CASE);

        return $classes + $functions;
    }

    /**
     * The sections of the page, for the navigation.
     *
     * @return list<array{string, string}>
     */
    public function anchors(ReportSite $site): array
    {
        $anchors = [];
        foreach (array_keys($site->index()->byNamespace()) as $namespace) {
            $label = Scope::of(($namespace === '' ? '' : $namespace . '\\') . 'x')->namespaceLabel();
            $anchors[] = [$label, 'ns-' . $this->text->slug($label)];
        }

        return $anchors;
    }
}
