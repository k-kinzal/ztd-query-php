<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html;

use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Catalog\Placeholder;
use SqlCatalog\Catalog\Resolution;
use SqlCatalog\Catalog\Severity;

/**
 * One statement, written the way the report shows it.
 *
 * What the statement is and what the analyzer managed to establish about it are
 * shown as two different things. The kind carries an identity hue because it is
 * a property of the statement; the resolution and the findings carry state hues
 * because they are judgements about the reading. A call that no statement was
 * read from is not dressed up as a statement at all.
 *
 * @visibility root
 */
final class StatementCard
{
    private HtmlText $text;

    private SqlHighlighter $sql;

    /**
     * Wires the card to the text and SQL rendering it is written with.
     */
    public function __construct(?HtmlText $text = null, ?SqlHighlighter $sql = null)
    {
        $this->text = $text ?? new HtmlText();
        $this->sql = $sql ?? new SqlHighlighter($this->text);
    }

    /**
     * The whole statement as one card.
     */
    public function render(CatalogEntry $entry): string
    {
        return '<article class="stmt" id="' . $this->text->escape($entry->id) . '" data-severity="'
            . $this->text->escape($entry->severity()->value) . '">'
            . $this->head($entry)
            . $this->body($entry)
            . $this->tables($entry)
            . $this->caveats($entry)
            . '<div class="stmt-blocks">' . $this->values($entry) . $this->findings($entry) . '</div>'
            . '</article>';
    }

    /**
     * The line naming what the statement is and where it is issued.
     */
    public function head(CatalogEntry $entry): string
    {
        $resolution = $entry->resolution();

        return '<header class="stmt-head">'
            . $this->text->chip(strtoupper($entry->kind->value), $this->kindRole($entry->kind->value))
            . $this->text->chip($resolution->value, $this->resolutionRole($resolution), $resolution->describe())
            . ($entry->severity()->atLeast(Severity::Medium)
                ? $this->text->chip($entry->severity()->value, $this->severityRole($entry->severity()))
                : '')
            . '<span class="stmt-site">' . $this->text->escape($entry->site->display()) . '</span>'
            . '<span class="stmt-fn">' . $this->text->escape($entry->site->function) . '</span>'
            . '<a class="anchor" href="#' . $this->text->escape($entry->id) . '" title="'
            . $this->text->escape($entry->id) . '">#</a>'
            . '</header>';
    }

    /**
     * The statement itself, or the call it was not read from.
     */
    public function body(CatalogEntry $entry): string
    {
        if ($entry->resolution() !== Resolution::NotAnalyzed) {
            return '<pre class="sql">' . $this->sql->render($entry->parts()) . '</pre>';
        }
        $written = $entry->firstGap()?->expression;

        return '<pre class="sql"><span class="tok-com">-- no statement was read from this call</span>'
            . ($written === null ? '' : "\n" . $this->text->escape($written)) . '</pre>';
    }

    /**
     * The tables the statement names, and the call it goes through.
     */
    public function tables(CatalogEntry $entry): string
    {
        $chips = '';
        foreach ($entry->tables as $table) {
            $chips .= $this->text->chip($table, 'chip-ghost') . ' ';
        }
        if ($entry->tables === [] && $entry->resolution() !== Resolution::NotAnalyzed) {
            $chips = '<span class="none">no table named</span> ';
        }

        return '<div class="stmt-tables">' . $chips
            . $this->text->chip($entry->site->sink, 'chip-sm', 'The database call that was matched') . '</div>';
    }

    /**
     * What a reader has to know before trusting the statement.
     */
    public function caveats(CatalogEntry $entry): string
    {
        $caveats = [];
        if ($entry->resolution() === Resolution::NotAnalyzed) {
            $caveats[] = $entry->resolution()->describe();
        } elseif (!$entry->resolution()->isClosed()) {
            $caveats[] = $entry->resolution()->describe() . ' The statements listed here may not be all of them.';
        }
        if (!$entry->correlated) {
            $caveats[] = 'Assembled from parts that vary independently, so some of these may be unreachable.';
        }
        if (count($entry->through) > 1) {
            $caveats[] = 'Read through ' . implode(' → ', $entry->through) . '.';
        }
        if ($caveats === []) {
            return '';
        }
        $items = '';
        foreach ($caveats as $caveat) {
            $items .= '<li>' . $this->text->escape($caveat) . '</li>';
        }

        return '<div class="notice notice-warn"><ul>' . $items . '</ul></div>';
    }

    /**
     * The bind parameters of the statement, with what each is bound to.
     */
    public function values(CatalogEntry $entry): string
    {
        if ($entry->placeholders === []) {
            return '';
        }
        $rows = '';
        foreach ($entry->placeholders as $placeholder) {
            $rows .= $this->valueRow($placeholder);
        }

        return '<section class="stmt-block"><h4>Values</h4><div class="table-wrap"><table>'
            . '<thead><tr><th class="tight">Parameter</th><th class="tight">Type</th><th>Bound to</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table></div></section>';
    }

    /**
     * One bind parameter as a row.
     */
    public function valueRow(Placeholder $placeholder): string
    {
        $value = $placeholder->value;
        $bound = $value === null
            ? '<span class="none">unbound</span>'
            : ($value->isResolved()
                ? '<code>' . $this->text->escape($value->display()) . '</code>'
                : '<span class="muted">' . $this->text->escape($this->openValue($value->origins)) . '</span>');

        return '<tr><td class="tight"><code>' . $this->text->escape($placeholder->token) . '</code></td>'
            . '<td class="tight"><code>' . $this->text->escape($value === null ? '?' : $value->type) . '</code></td>'
            . '<td>' . $bound . '</td></tr>';
    }

    /**
     * How a value that did not resolve to a set of alternatives is described.
     *
     * @param list<string> $origins
     */
    public function openValue(array $origins): string
    {
        return $origins === [] ? 'not pinned down' : 'not pinned down: ' . implode(', ', $origins);
    }

    /**
     * What is worth reporting about the statement.
     */
    public function findings(CatalogEntry $entry): string
    {
        if ($entry->findings === []) {
            return '';
        }
        $items = '';
        foreach ($entry->findings as $finding) {
            $items .= '<li>' . $this->text->chip($finding->severity->value, $this->severityRole($finding->severity))
                . '<span>' . $this->text->escape($finding->message) . '</span></li>';
        }

        return '<section class="stmt-block"><h4>Findings</h4><ul class="finding-list">' . $items . '</ul></section>';
    }

    /**
     * The identity hue a statement of that kind is written in.
     */
    public function kindRole(string $kind): string
    {
        return match ($kind) {
            'select' => 'k-select',
            'insert', 'replace', 'merge' => 'k-insert',
            'update' => 'k-update',
            'delete' => 'k-delete',
            'create', 'alter', 'drop', 'truncate' => 'k-schema',
            default => 'k-other',
        };
    }

    /**
     * The state hue a resolution is written in.
     */
    public function resolutionRole(Resolution $resolution): string
    {
        return match ($resolution) {
            Resolution::Resolved => 's-ok',
            Resolution::ExternalInput => 's-danger',
            Resolution::IncompleteModel, Resolution::Incomplete => 's-warn',
            Resolution::NotAnalyzed => 's-neutral',
        };
    }

    /**
     * The state hue a severity is written in.
     */
    public function severityRole(Severity $severity): string
    {
        return match ($severity) {
            Severity::High => 's-danger',
            Severity::Medium => 's-warn',
            Severity::Low, Severity::Info => 's-neutral',
        };
    }
}
