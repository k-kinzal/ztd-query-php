<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html\Page;

use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Catalog\Placeholder;
use SqlCatalog\Catalog\Resolution;
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\Palette;
use SqlCatalog\Reporter\Html\ReportSite;
use SqlCatalog\Reporter\Html\Scope;
use SqlCatalog\Reporter\Html\SqlFormatter;
use SqlCatalog\Reporter\Html\SqlHighlighter;
use SqlCatalog\Reporter\Html\StatementList;
use SqlCatalog\Reporter\Html\TableName;

/**
 * One statement, with everything the analysis established about it.
 *
 * The statement comes first and largest, laid out a clause per line, because
 * it is what the reader came for. Around it are the facts that decide what
 * to do about it: where it is issued and through what, what it names, what
 * is bound to it, and what the analyzer could not settle. The other
 * statements of the same function and on the same table are listed last,
 * so the reading can continue without going back.
 *
 * @visibility root
 */
final class StatementPage
{
    /**
     * How many related statements are listed before pointing at their own page.
     */
    public const RELATED = 8;

    private HtmlText $text;

    private SqlHighlighter $sql;

    private SqlFormatter $formatter;

    private StatementList $list;

    private Palette $palette;

    /**
     * Wires the page to the rendering it is written with.
     */
    public function __construct(
        ?HtmlText $text = null,
        ?SqlHighlighter $sql = null,
        ?SqlFormatter $formatter = null,
        ?StatementList $list = null,
        ?Palette $palette = null,
    ) {
        $this->text = $text ?? new HtmlText();
        $this->sql = $sql ?? new SqlHighlighter($this->text);
        $this->formatter = $formatter ?? new SqlFormatter();
        $this->list = $list ?? new StatementList($this->text);
        $this->palette = $palette ?? new Palette();
    }

    /**
     * The body of one statement's page.
     */
    public function render(ReportSite $site, CatalogEntry $entry): string
    {
        $page = $site->statementPage($entry->id);

        return '<h1>' . $this->text->chip(strtoupper($entry->kind->value), $this->palette->kind($entry->kind->value))
            . '<span>' . $this->text->escape($this->title($entry)) . '</span></h1>'
            . '<p class="lede">' . $this->where($site, $entry) . '</p>'
            . $this->body($entry)
            . $this->caveats($entry)
            . ($entry->placeholders === []
                ? $this->facts($site, $entry)
                : '<div class="split">' . $this->facts($site, $entry) . $this->values($entry) . '</div>')
            . $this->findings($entry)
            . $this->related($site, $page, $entry);
    }

    /**
     * What the statement is, in a few words.
     */
    public function title(CatalogEntry $entry): string
    {
        if ($entry->resolution() === Resolution::NotAnalyzed) {
            return 'a call nothing was read from';
        }
        if ($entry->tables === []) {
            return 'no table named';
        }
        $labels = [];
        foreach ($entry->tables as $table) {
            $labels[] = (new TableName($table))->label();
        }

        return 'on ' . implode(', ', $labels);
    }

    /**
     * Where the statement is issued, as a sentence whose every place is a link.
     */
    public function where(ReportSite $site, CatalogEntry $entry): string
    {
        $scope = Scope::of($entry->site->function);
        $sentence = 'Issued at ' . $this->text->link($entry->site->display(), '../' . $site->filePage($entry->site->file), 'mono');
        if (!$scope->isMain()) {
            $sentence .= ' in ' . $this->text->link($scope->function(), '../' . $site->functionUrl($entry), 'mono');
        }
        $sentence .= ' through ' . $this->text->chip($entry->site->sink, 'chip-sm', 'The database call that was matched');
        if (count($entry->through) > 1) {
            $sentence .= ', reached by way of <code>' . $this->text->escape(implode(' → ', $entry->through)) . '</code>';
        }

        return $sentence . '.';
    }

    /**
     * The statement itself, laid out for reading, or the call it was not read from.
     */
    public function body(CatalogEntry $entry): string
    {
        if ($entry->resolution() === Resolution::NotAnalyzed) {
            $written = $entry->firstGap()?->expression;

            return '<pre class="sql sql-full"><span class="tok-com">-- no statement was read from this call</span>'
                . ($written === null ? '' : "\n" . $this->text->escape($written)) . '</pre>';
        }

        return '<div class="sql-block">'
            . '<button type="button" class="copy" data-copy="sql-text" title="Copy the statement">Copy</button>'
            . '<pre class="sql sql-full">' . $this->sql->render($this->formatter->format($entry->parts())) . '</pre>'
            . '<textarea id="sql-text" hidden readonly>' . $this->text->escape($entry->sql()) . '</textarea>'
            . '<details class="as-written"><summary>As written in the source</summary><pre class="sql">'
            . $this->sql->render($entry->parts()) . '</pre></details></div>';
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
            $caveats[] = $entry->resolution()->describe() . ' The statements listed for this call may not be all of them.';
        } elseif ($entry->truncated) {
            $caveats[] = 'A limit on loop passes or on callers cut the search short. The statements listed for this call may not be all of them.';
        }
        if (!$entry->correlated) {
            $caveats[] = 'Assembled from parts that vary independently, so some of the alternatives at this call may be unreachable.';
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
     * The facts about the reading, as a list of labelled values.
     */
    public function facts(ReportSite $site, CatalogEntry $entry): string
    {
        $resolution = $entry->resolution();
        $tables = '';
        foreach ($entry->tables as $table) {
            $tables .= $this->text->chipLink((new TableName($table))->label(), '../' . $site->tablePage($table), 'chip-ghost') . ' ';
        }
        $rows = [
            ['Resolution', $this->text->chip($resolution->value, $this->palette->resolution($resolution)) . ' <span class="muted">' . $this->text->escape($resolution->describe()) . '</span>'],
            ['Search', $entry->searchClosed()
                ? '<span class="muted">closed: every dependency was followed to its end</span>'
                : '<span class="muted">left open: the listing for this call is a lower bound</span>'],
            ['Tables', $tables === '' ? '<span class="none">none named</span>' : $tables],
            ['Kind', $this->text->chip(strtoupper($entry->kind->value), $this->palette->kind($entry->kind->value))],
            ['Identifier', '<code>' . $this->text->escape($entry->id) . '</code> <span class="muted">stable across runs while the statement is unchanged</span>'],
        ];
        $written = '';
        foreach ($rows as [$label, $value]) {
            $written .= '<div><dt>' . $this->text->escape($label) . '</dt><dd>' . $value . '</dd></div>';
        }

        return '<section><h2 id="facts">About this statement</h2><dl class="facts-grid">' . $written . '</dl></section>';
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

        return '<section><h2 id="values">Bound values</h2><div class="table-wrap"><table>'
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
            $items .= '<li>' . $this->text->chip($finding->severity->value, $this->palette->severity($finding->severity))
                . '<span><code>' . $this->text->escape($finding->rule->value) . '</code> ' . $this->text->escape($finding->message) . '</span></li>';
        }

        return '<h2 id="findings">Findings</h2><ul class="finding-list">' . $items . '</ul>';
    }

    /**
     * The other statements of the same function, and on the same tables.
     */
    public function related(ReportSite $site, string $page, CatalogEntry $entry): string
    {
        $index = $site->index();
        $scope = Scope::of($entry->site->function);
        $written = '';
        $siblings = array_values(array_filter($index->byFunction()[$entry->site->function] ?? [], static fn (CatalogEntry $other): bool => $other->id !== $entry->id));
        if ($siblings !== [] && !$scope->isMain()) {
            $written .= '<section><h2 id="same-function">Also issued by <code>' . $this->text->escape($scope->display()) . '</code>'
                . $this->text->count(count($siblings)) . '</h2>'
                . $this->list->rows($site, $page, array_slice($siblings, 0, self::RELATED), ['function'])
                . (count($siblings) > self::RELATED ? '<p class="route-all">' . $this->text->link('Every statement of this function', '../' . $site->functionUrl($entry)) . '</p>' : '')
                . '</section>';
        }
        foreach (array_slice($entry->tables, 0, 2) as $table) {
            $others = array_values(array_filter($index->byTable()[$table] ?? [], static fn (CatalogEntry $other): bool => $other->id !== $entry->id));
            if ($others === []) {
                continue;
            }
            $written .= '<section><h2 id="' . $this->text->escape('same-table-' . $this->text->slug($table)) . '">Also on '
                . $this->text->chipLink((new TableName($table))->label(), '../' . $site->tablePage($table), 'chip-ghost') . $this->text->count(count($others)) . '</h2>'
                . $this->list->rows($site, $page, array_slice($others, 0, self::RELATED))
                . (count($others) > self::RELATED ? '<p class="route-all">' . $this->text->link('Every statement on this table', '../' . $site->tablePage($table)) . '</p>' : '')
                . '</section>';
        }

        return $written;
    }
}
