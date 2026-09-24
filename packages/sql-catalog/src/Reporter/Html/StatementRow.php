<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html;

use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Catalog\Resolution;
use SqlCatalog\Catalog\Severity;

/**
 * One statement as a row of a listing.
 *
 * A row is a doc-ui listing row. A listing is for scanning, so a row shows
 * the statement itself first — the SQL, formatted across multiple lines — and
 * then where it is issued, with every place it names linked to the page for
 * it. What the analyzer thinks of
 * the statement is shown only when it is not the ordinary case: a resolved
 * statement with nothing reported carries no state chips at all, so the ones
 * that do stand out.
 *
 * @visibility root
 */
final class StatementRow
{
    private HtmlText $text;

    private SqlHighlighter $sql;

    private Palette $palette;

    private SqlFormatter $formatter;

    /**
     * Wires the row to the text and SQL rendering it is written with.
     */
    public function __construct(?HtmlText $text = null, ?SqlHighlighter $sql = null, ?Palette $palette = null, ?SqlFormatter $formatter = null)
    {
        $this->text = $text ?? new HtmlText();
        $this->sql = $sql ?? new SqlHighlighter($this->text);
        $this->palette = $palette ?? new Palette();
        $this->formatter = $formatter ?? new SqlFormatter();
    }

    /**
     * The row, with its links written relative to the page it is on.
     *
     * @param list<string> $omit The facts the listing already states, out of `function`, `file` and `tables`
     */
    public function render(ReportSite $site, string $page, CatalogEntry $entry, array $omit = []): string
    {
        $prefix = $site->prefixOf($page);

        return '<li class="row"' . $this->attributes($entry) . '>'
            . '<a class="row-main" href="' . $this->text->escape($prefix . $site->statementPage($entry->id)) . '">'
            . $this->text->chip(strtoupper($entry->kind->value), $this->palette->kind($entry->kind->value))
            . '<span class="row-body">' . $this->sql($entry) . '</span></a>'
            . '<p class="row-meta">' . $this->meta($site, $prefix, $entry, $omit) . '</p>'
            . '</li>';
    }

    /**
     * The facts a listing can be narrowed by, written as attributes.
     */
    public function attributes(CatalogEntry $entry): string
    {
        $scope = Scope::of($entry->site->function);
        $rules = [];
        foreach ($entry->findings as $finding) {
            $rules[] = $finding->rule->value;
        }
        $facts = [
            'kind' => $entry->kind->value,
            'resolution' => $entry->resolution()->value,
            'severity' => $entry->findings === [] ? '' : $entry->severity()->value,
            'rule' => implode(' ', array_unique($rules)),
            'sink' => $entry->site->sink,
            'open' => $entry->searchClosed() ? '' : 'open',
            'table' => implode(' ', $entry->tables),
            'namespace' => $scope->namespace,
            'class' => $scope->class ?? '',
            'function' => $entry->site->function,
            'file' => $entry->site->file,
        ];
        $written = '';
        foreach ($facts as $name => $value) {
            $written .= ' data-' . $name . '="' . $this->text->escape($value) . '"';
        }

        return $written;
    }

    /**
     * The formatted statement, or the call it was not read from.
     */
    public function sql(CatalogEntry $entry): string
    {
        if ($entry->resolution() !== Resolution::NotAnalyzed) {
            return $this->sql->render($this->formatter->format($entry->parts()));
        }
        $written = $entry->firstGap()?->expression;

        return '<span class="tok-com">no statement was read from this call</span>'
            . ($written === null ? '' : ' ' . $this->text->escape($written));
    }

    /**
     * Where the statement is issued and what is known about the reading.
     *
     * @param list<string> $omit
     */
    public function meta(ReportSite $site, string $prefix, CatalogEntry $entry, array $omit): string
    {
        $scope = Scope::of($entry->site->function);
        $items = [];
        if (!in_array('file', $omit, true)) {
            $items[] = $this->text->link($entry->site->display(), $prefix . $site->filePage($entry->site->file));
        }
        if (!in_array('function', $omit, true) && !$scope->isMain()) {
            $items[] = $this->text->link($scope->display(), $prefix . $site->functionUrl($entry));
        }
        if (!in_array('tables', $omit, true)) {
            foreach ($entry->tables as $table) {
                $items[] = $this->text->chipLink($table, $prefix . $site->tablePage($table), 'chip-ghost');
            }
        }
        $resolution = $entry->resolution();
        if ($resolution !== Resolution::Resolved) {
            $items[] = $this->text->chip($resolution->value, $this->palette->resolution($resolution), $resolution->describe());
        }
        if ($entry->findings !== [] && $entry->severity()->atLeast(Severity::Medium)) {
            $items[] = $this->text->chip($entry->severity()->value, $this->palette->severity($entry->severity()), 'The most serious finding on this statement');
        }

        return implode('', $items);
    }
}
