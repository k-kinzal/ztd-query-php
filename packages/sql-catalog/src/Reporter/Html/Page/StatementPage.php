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
use SqlCatalog\Reporter\Html\Source\SourceCode;
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

    /**
     * How much of a statement the navigation beside the page shows of it.
     */
    public const LABEL = 40;

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
            . (new SourceCode($this->text))->excerpt($site, $entry)
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
        $sentence .= ' through ' . $this->text->chip($entry->site->sink, 'chip-sm chip-ghost', 'The database call that was matched');
        if (count($entry->through) > 1) {
            $sentence .= ', reached by way of <code>' . $this->text->escape(implode(' → ', $entry->through)) . '</code>';
        }

        return $sentence . '.';
    }

    /**
     * The statement itself, laid out for reading, or the call it was not read from.
     *
     * The copy button takes what the block shows, so what is copied is what
     * was read: the statement a clause per line, with every gap as `{$}`.
     */
    public function body(CatalogEntry $entry): string
    {
        if ($entry->resolution() === Resolution::NotAnalyzed) {
            $written = $entry->firstGap()?->expression;

            return '<pre class="code code-lead"><span class="tok-com">-- no statement was read from this call</span>'
                . ($written === null ? '' : "\n" . $this->text->escape($written)) . '</pre>';
        }

        return '<div class="code-block">'
            . '<button type="button" class="btn copy" data-dd-copy title="Copy the statement">Copy</button>'
            . '<pre class="code code-lead">' . $this->sql->render($this->formatter->format($entry->parts())) . '</pre>'
            . '<details class="as-written"><summary>As written in the source</summary><pre class="code">'
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

        return '<div class="notice tone-warn"><ul>' . $items . '</ul></div>';
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
                : '<span class="muted">left open: candidates or dependencies remain unknown</span>'],
            ['Reachability', '<span class="muted">not assessed: both branches are retained, including constant conditions</span>'],
            ['Tables', $tables === '' ? '<span class="none">none named</span>' : $tables],
            ['Kind', $this->text->chip(strtoupper($entry->kind->value), $this->palette->kind($entry->kind->value))],
            ['Identifier', '<code>' . $this->text->escape($entry->id) . '</code> <span class="muted">stable across runs while the statement is unchanged</span>'],
        ];
        $written = '';
        foreach ($rows as [$label, $value]) {
            $written .= '<div><dt>' . $this->text->escape($label) . '</dt><dd>' . $value . '</dd></div>';
        }

        return '<section><h2 id="facts">About this statement</h2><dl class="facts">' . $written . '</dl></section>';
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
            . '<thead><tr><th scope="col" class="tight">Parameter</th><th scope="col" class="tight">Type</th><th scope="col">Bound to</th></tr></thead>'
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
     * What the page is best left from: the places the statement belongs to, and the rest of its function.
     *
     * @return list<array{string, list<array{string, string, int|null, bool}>, string|null}>
     */
    public function context(ReportSite $site, CatalogEntry $entry): array
    {
        $index = $site->index();
        $scope = Scope::of($entry->site->function);
        $places = [];
        if ($site->catalog()->source($entry->site->file) !== null) {
            $places[] = ['Source code', '#source', null, false];
        }
        foreach ($entry->tables as $table) {
            $places[] = ['Table ' . (new TableName($table))->label(), $site->tablePage($table), count($index->byTable()[$table] ?? []), false];
        }
        if ($scope->class !== null) {
            $places[] = ['Class ' . ($scope->classShort() ?? $scope->class), $site->classPage($scope->class), count($index->byClass()[$scope->class] ?? []), false];
        }
        if (!$scope->isMain()) {
            $places[] = [$scope->display() . '()', $site->functionUrl($entry), count($index->byFunction()[$entry->site->function] ?? []), false];
        }
        $places[] = ['File ' . $entry->site->file, $site->filePage($entry->site->file), count($index->byFile()[$entry->site->file] ?? []), false];
        $siblings = [];
        foreach ($index->byFunction()[$entry->site->function] ?? [] as $other) {
            $siblings[] = [$this->text->truncate($other->sql(), self::LABEL), $site->statementPage($other->id), null, $other->id === $entry->id];
        }

        return [['Belongs to', $places, null], ['Statements of ' . $scope->display(), $siblings, $site->functionUrl($entry)]];
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
                . (count($siblings) > self::RELATED ? '<p class="more">' . $this->text->link('Every statement of this function', '../' . $site->functionUrl($entry)) . '</p>' : '')
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
                . (count($others) > self::RELATED ? '<p class="more">' . $this->text->link('Every statement on this table', '../' . $site->tablePage($table)) . '</p>' : '')
                . '</section>';
        }

        return $written;
    }
}
