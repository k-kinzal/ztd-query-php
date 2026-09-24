<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html\Page;

use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\ReportSite;
use SqlCatalog\Reporter\Html\TableName;

/**
 * Every table the catalog names, with how each is used.
 *
 * A table is the unit a schema change is made in, so the listing answers the
 * question that precedes one: how much reads this, how much writes it, and
 * how much of that the analysis is unsure about. Tables qualified with a
 * schema are listed under it, so a catalog spanning databases reads database
 * by database.
 *
 * @visibility root
 */
final class TableIndexPage
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
     * The body of the tables listing.
     */
    public function render(ReportSite $site): string
    {
        $tables = $site->index()->byTable();
        if ($tables === []) {
            return '<h1>Tables</h1><p class="lede">No statement names a table.</p>';
        }
        $groups = $this->bySchema($tables);
        $sections = '';
        foreach ($groups as $schema => $named) {
            $label = $schema === '' ? 'Unqualified' : $schema;
            $sections .= (count($groups) > 1 ? '<h2 id="' . $this->text->escape('schema-' . $this->text->slug($label)) . '">'
                . $this->text->escape($label) . $this->text->count(count($named), 'table') . '</h2>' : '')
                . $this->table($site, $named);
        }

        return '<h1>Tables' . $this->text->count(count($tables), 'table') . '</h1>'
            . '<p class="lede">Every table the statements name, most named first. A join counts for each of its tables. '
            . 'Open a table to see what reads, writes and alters it, and from where.</p>'
            . '<input type="search" name="filter" class="input input-block" data-filter-rows placeholder="Narrow by table name…" aria-label="Narrow by table name" autocomplete="off" spellcheck="false">'
            . $sections;
    }

    /**
     * The tables under each schema, the unqualified ones under an empty name.
     *
     * @param array<string, list<CatalogEntry>> $tables
     * @return array<string, array<string, list<CatalogEntry>>>
     */
    public function bySchema(array $tables): array
    {
        $groups = [];
        foreach ($tables as $table => $entries) {
            $groups[(new TableName($table))->schema() ?? ''][$table] = $entries;
        }
        ksort($groups, SORT_STRING);

        return $groups;
    }

    /**
     * One listing of tables, sortable by any column.
     *
     * @param array<string, list<CatalogEntry>> $tables
     */
    public function table(ReportSite $site, array $tables): string
    {
        $rows = '';
        foreach ($tables as $table => $entries) {
            $rows .= $this->row($site, $table, $entries);
        }

        return '<div class="table-wrap"><table class="sortable filter-target" data-dd-sortable><thead><tr>'
            . '<th scope="col" data-dd-sort="text">Table</th><th scope="col" class="num" data-dd-sort="number">Statements</th>'
            . '<th scope="col" class="num" data-dd-sort="number">Reads</th><th scope="col" class="num" data-dd-sort="number">Writes</th>'
            . '<th scope="col" class="num" data-dd-sort="number">Schema</th><th scope="col" class="num" data-dd-sort="number">Attention</th>'
            . '<th scope="col" class="num" data-dd-sort="number">Functions</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    /**
     * One table as a row.
     *
     * @param list<CatalogEntry> $entries
     */
    public function row(ReportSite $site, string $table, array $entries): string
    {
        $index = $site->index();
        $usage = $index->usage($entries);
        $name = new TableName($table);
        $cells = [count($entries), $usage['reads'], $usage['writes'], $usage['schema'], $usage['attention'], count($index->functionsOf($entries))];
        $written = '';
        foreach ($cells as $cell) {
            $written .= '<td class="num">' . ($cell === 0 ? '<span class="none">0</span>' : $this->text->number($cell)) . '</td>';
        }

        return '<tr><td><a class="' . ($name->isUnknown() ? 'muted' : 'mono') . '" href="' . $this->text->escape($site->tablePage($table)) . '">'
            . ($name->isUnknown() ? $this->text->escape($name->label()) : $this->text->marked($table)) . '</a></td>' . $written . '</tr>';
    }
}
