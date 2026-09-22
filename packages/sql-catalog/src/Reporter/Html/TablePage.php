<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html;

use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Catalog\CatalogEntry;

/**
 * Every table the catalog names, with the statements that read and write it.
 *
 * Reading a table and writing it are listed side by side because that is the
 * question a catalog is usually opened with: what puts rows into this table,
 * and does anything read them back the way they were written.
 *
 * @visibility root
 */
final class TablePage
{
    private HtmlText $text;

    private StatementCard $card;

    /**
     * Wires the page to the rendering it is written with.
     */
    public function __construct(?HtmlText $text = null, ?StatementCard $card = null)
    {
        $this->text = $text ?? new HtmlText();
        $this->card = $card ?? new StatementCard($this->text);
    }

    /**
     * The body of the tables page.
     */
    public function render(ReportSite $site, Catalog $catalog, CatalogStatistics $stats): string
    {
        $tables = $stats->tables();
        if ($tables === []) {
            return '<h1>Tables</h1><p class="lede">No statement names a table.</p>';
        }
        $byTable = $this->group($catalog);

        $sections = '';
        foreach ($tables as $table) {
            $sections .= $this->section($site, $table, $byTable[$table['name']] ?? []);
        }

        return '<h1>Tables <span class="count">' . $this->text->escape($this->text->plural(count($tables), 'table'))
            . '</span></h1>'
            . '<p class="lede">Every table the statements name. A table is counted once per statement that names it, '
            . 'so a join counts for each of its tables.</p>'
            . $sections;
    }

    /**
     * The statements naming each table, in reporting order.
     *
     * @return array<string, list<CatalogEntry>>
     */
    public function group(Catalog $catalog): array
    {
        $grouped = [];
        foreach ($catalog->sorted() as $entry) {
            foreach ($entry->tables as $table) {
                $grouped[$table][] = $entry;
            }
        }

        return $grouped;
    }

    /**
     * One table, with the statements that name it.
     *
     * @param array{name: string, reads: int, writes: int, statements: int} $table
     * @param list<CatalogEntry> $entries
     */
    public function section(ReportSite $site, array $table, array $entries): string
    {
        $rows = '';
        foreach ($entries as $entry) {
            $rows .= $this->row($site, $entry);
        }

        return '<details class="usage" id="table-' . $this->text->escape($this->text->slug($table['name'])) . '">'
            . '<summary><code>' . $this->text->escape($table['name']) . '</code>'
            . '<span class="count">' . $this->text->escape($this->text->plural($table['statements'], 'statement'))
            . ' · ' . $this->text->number($table['reads']) . ' read · '
            . $this->text->number($table['writes']) . ' written</span></summary>'
            . '<div class="table-wrap"><table><thead><tr><th class="tight">Kind</th><th>Statement</th>'
            . '<th class="tight">Where</th></tr></thead><tbody>' . $rows . '</tbody></table></div></details>';
    }

    /**
     * One statement as a row of a table's listing.
     */
    public function row(ReportSite $site, CatalogEntry $entry): string
    {
        return '<tr><td class="tight">'
            . $this->text->chip(strtoupper($entry->kind->value), $this->card->kindRole($entry->kind->value)) . '</td>'
            . '<td><a class="stmt-link" href="' . $this->text->escape($site->urlOf($entry->id)) . '"><code>'
            . $this->text->escape($this->text->truncate($entry->sql(), 110)) . '</code></a></td>'
            . '<td class="tight"><span class="stmt-site">' . $this->text->escape($entry->site->display())
            . '</span></td></tr>';
    }
}
