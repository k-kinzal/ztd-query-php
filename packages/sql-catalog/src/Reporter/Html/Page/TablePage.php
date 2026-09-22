<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html\Page;

use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Reporter\Html\CatalogIndex;
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\ReportSite;
use SqlCatalog\Reporter\Html\Scope;
use SqlCatalog\Reporter\Html\StatementList;
use SqlCatalog\Reporter\Html\TableName;

/**
 * One table: what reads it, what writes it, what alters it, and from where.
 *
 * This is the page opened before a column is renamed or an index dropped.
 * The writes are listed before the reads because they are the ones a schema
 * change breaks first, and the functions issuing them are listed by
 * themselves because the places to edit matter as much as the statements.
 *
 * @visibility root
 */
final class TablePage
{
    /**
     * The groups a table's statements are listed in, in order.
     */
    public const GROUPS = ['writes' => 'Writes', 'reads' => 'Reads', 'schema' => 'Schema changes', 'other' => 'Other statements'];

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
     * The body of one table's page.
     */
    public function render(ReportSite $site, string $table): string
    {
        $index = $site->index();
        $entries = $index->byTable()[$table] ?? [];
        $name = new TableName($table);
        $usage = $index->usage($entries);

        return '<h1>' . ($name->isUnknown() ? $this->text->escape($name->label()) : '<code>' . $this->text->marked($table) . '</code>')
            . $this->text->count(count($entries), 'statement') . '</h1>'
            . '<p class="lede">' . $this->text->escape($this->summary($name, $usage)) . '</p>'
            . $this->usedFrom($site, $entries)
            . $this->alongside($site, $table)
            . '<h2 id="statements">Statements</h2>'
            . '<div class="filterable" data-narrowable>' . $this->list->facets($entries) . $this->groups($site, $table, $entries) . '</div>';
    }

    /**
     * How the table is used, in one sentence.
     *
     * @param array{reads: int, writes: int, schema: int, other: int, attention: int} $usage
     */
    public function summary(TableName $name, array $usage): string
    {
        $parts = [];
        foreach (['reads' => 'read by', 'writes' => 'written by', 'schema' => 'altered by', 'other' => 'otherwise named by'] as $key => $verb) {
            if ($usage[$key] > 0) {
                $parts[] = $verb . ' ' . $this->text->plural($usage[$key], 'statement');
            }
        }
        $sentence = ($name->isUnknown() ? 'A table whose name the analysis could not pin down, ' : 'This table is ') . implode(', ', $parts) . '.';
        if ($usage['attention'] > 0) {
            $sentence .= ' ' . $this->text->plural($usage['attention'], 'statement') . ' carry a finding worth looking at.';
        }

        return $sentence;
    }

    /**
     * The functions issuing statements on the table, most first.
     *
     * @param list<CatalogEntry> $entries
     */
    public function usedFrom(ReportSite $site, array $entries): string
    {
        $index = $site->index();
        $byFunction = [];
        foreach ($entries as $entry) {
            $byFunction[$entry->site->function][] = $entry;
        }
        $rows = '';
        foreach ($index->functionsOf($entries) as $function => $count) {
            $first = $byFunction[$function][0];
            $usage = $index->usage($byFunction[$function]);
            $rows .= '<tr><td>' . $this->text->link(Scope::of($function)->display(), '../' . $site->functionUrl($first), 'mono') . '</td>'
                . '<td>' . $this->text->link($first->site->file, '../' . $site->filePage($first->site->file), 'muted') . '</td>'
                . '<td class="num">' . $this->text->number($count) . '</td>'
                . '<td class="tight">' . $this->text->escape($this->verbs($usage)) . '</td></tr>';
        }

        return '<h2 id="used-from">Used from' . $this->text->count(count($byFunction), 'function') . '</h2>'
            . '<div class="table-wrap"><table class="sortable"><thead><tr><th data-sort="text">Function</th><th data-sort="text">File</th>'
            . '<th class="num" data-sort="num">Statements</th><th class="tight">Does</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    /**
     * What a set of statements does to the table, as a short phrase.
     *
     * @param array{reads: int, writes: int, schema: int, other: int, attention: int} $usage
     */
    public function verbs(array $usage): string
    {
        $verbs = [];
        foreach (['reads' => 'reads', 'writes' => 'writes', 'schema' => 'alters', 'other' => 'other'] as $key => $verb) {
            if ($usage[$key] > 0) {
                $verbs[] = $verb;
            }
        }

        return implode(', ', $verbs);
    }

    /**
     * The tables named in the same statements, most often first.
     */
    public function alongside(ReportSite $site, string $table): string
    {
        $chips = '';
        foreach ($site->index()->alongside($table) as $other => $count) {
            $chips .= '<li>' . $this->text->chipLink((new TableName($other))->label(), '../' . $site->tablePage($other), 'chip-ghost')
                . '<span class="route-figures">' . $this->text->number($count) . '</span></li>';
        }
        if ($chips === '') {
            return '';
        }

        return '<h2 id="alongside">Named alongside</h2><p class="lede">Tables that appear in the same statements, usually through a join.</p>'
            . '<ol class="route-top route-chips">' . $chips . '</ol>';
    }

    /**
     * The statements in the groups a schema change is reasoned about in.
     *
     * @param list<CatalogEntry> $entries
     */
    public function groups(ReportSite $site, string $table, array $entries): string
    {
        $index = $site->index();
        $grouped = [];
        foreach ($entries as $entry) {
            $grouped[$index->usageOf($entry)][] = $entry;
        }
        $sections = '';
        foreach (self::GROUPS as $key => $label) {
            if (!isset($grouped[$key])) {
                continue;
            }
            $sections .= '<section class="group" id="' . $key . '"><h3>' . $this->text->escape($label)
                . $this->text->count(count($grouped[$key])) . '</h3>'
                . $this->list->rows($site, $site->tablePage($table), $grouped[$key]) . '</section>';
        }

        return $sections;
    }

    /**
     * The sections of the page, for the navigation.
     *
     * @return list<array{string, string}>
     */
    public function anchors(CatalogIndex $index, string $table): array
    {
        $anchors = [['Used from', 'used-from']];
        if ($index->alongside($table) !== []) {
            $anchors[] = ['Named alongside', 'alongside'];
        }
        $present = [];
        foreach ($index->byTable()[$table] ?? [] as $entry) {
            $present[$index->usageOf($entry)] = true;
        }
        foreach (self::GROUPS as $key => $label) {
            if (isset($present[$key])) {
                $anchors[] = [$label, $key];
            }
        }

        return $anchors;
    }
}
