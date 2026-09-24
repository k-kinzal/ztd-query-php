<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html\Page;

use SqlCatalog\Catalog\FindingRule;
use SqlCatalog\Catalog\Resolution;
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\Palette;
use SqlCatalog\Reporter\Html\ReportSite;
use SqlCatalog\Reporter\Html\Scope;
use SqlCatalog\Reporter\Html\TableName;

/**
 * The page a reader opens first: the routes to a statement, and what needs looking at.
 *
 * A reader comes to the catalog with a question — what touches this table,
 * what does this class issue, where is SQL built from values the program does
 * not control — and the overview is laid out as those routes rather than as a
 * summary of counts. Every figure on it is a link to the statements it counts,
 * so a number is never the end of a reading.
 *
 * @visibility root
 */
final class OverviewPage
{
    /**
     * How many entries a route shows before pointing at its own page.
     */
    public const TOP = 6;

    private HtmlText $text;

    private Palette $palette;

    /**
     * Wires the page to the rendering it is written with.
     */
    public function __construct(?HtmlText $text = null, ?Palette $palette = null)
    {
        $this->text = $text ?? new HtmlText();
        $this->palette = $palette ?? new Palette();
    }

    /**
     * The body of the overview.
     */
    public function render(ReportSite $site): string
    {
        return '<h1>Overview</h1>'
            . '<p class="lede">Every statement this source can issue, read back from the calls that receive it. '
            . 'Start from the table, class or file you are working on, or from what the analysis flagged.</p>'
            . $this->facts($site)
            . '<div class="cards">' . $this->tableRoute($site) . $this->namespaceRoute($site)
            . $this->fileRoute($site) . $this->kindRoute($site) . '</div>'
            . $this->attention($site)
            . $this->coverage($site)
            . $this->problems($site);
    }

    /**
     * What the catalog holds, as a row of figures each leading to the listing it counts.
     */
    public function facts(ReportSite $site): string
    {
        $index = $site->index();
        $facts = [
            [$site->statistics()->statements(), 'statement', ReportSite::STATEMENTS],
            [count($site->tables()), 'table', ReportSite::TABLES],
            [count($index->byFunction()), 'function', ReportSite::NAMESPACES],
            [count($site->files()), 'file', ReportSite::FILES],
        ];
        $written = '';
        foreach ($facts as [$count, $noun, $href]) {
            $written .= '<a class="stat" href="' . $this->text->escape($href) . '"><b class="stat-fig">' . $this->text->number($count) . '</b>'
                . '<span class="stat-label">' . $this->text->escape($this->text->noun($count, $noun)) . '</span></a>';
        }

        return '<div class="stats">' . $written . '</div>';
    }

    /**
     * The route by table: what reads and writes each one.
     */
    public function tableRoute(ReportSite $site): string
    {
        $index = $site->index();
        $items = '';
        foreach (array_slice($index->byTable(), 0, self::TOP, true) as $table => $entries) {
            $usage = $index->usage($entries);
            $items .= '<li>' . $this->text->link((new TableName($table))->label(), $site->tablePage($table), 'mono')
                . '<span class="usage-kind">' . $this->text->number(count($entries)) . ' · '
                . $this->text->number($usage['reads']) . ' read · ' . $this->text->number($usage['writes']) . ' write</span></li>';
        }

        return $this->route(
            'Tables',
            ReportSite::TABLES,
            count($site->tables()),
            'table',
            'Which statements read, write or alter a table, and where each is issued. Start here before changing a schema.',
            $items
        );
    }

    /**
     * The route by namespace: what each class and function issues.
     */
    public function namespaceRoute(ReportSite $site): string
    {
        $index = $site->index();
        $classes = $index->byClass();
        uasort($classes, static fn (array $left, array $right): int => count($right) <=> count($left));
        $items = '';
        foreach (array_slice($classes, 0, self::TOP, true) as $class => $entries) {
            $items .= '<li>' . $this->text->link($class, $site->classPage($class), 'mono')
                . '<span class="usage-kind">' . $this->text->plural(count($entries), 'statement') . '</span></li>';
        }
        if ($items === '') {
            foreach (array_slice($index->mostFirst(array_map('count', $index->byFunction())), 0, self::TOP, true) as $function => $count) {
                $items .= '<li>' . $this->text->link(Scope::of($function)->display(), ReportSite::STATEMENTS . '?function=' . rawurlencode($function), 'mono')
                    . '<span class="usage-kind">' . $this->text->plural($count, 'statement') . '</span></li>';
            }
        }

        return $this->route(
            'Namespaces',
            ReportSite::NAMESPACES,
            count($index->byNamespace()),
            'namespace',
            'The statements each class and function issues, method by method. Start here before refactoring code that talks to the database.',
            $items
        );
    }

    /**
     * The route by file: what is written where.
     */
    public function fileRoute(ReportSite $site): string
    {
        $files = $site->index()->byFile();
        uasort($files, static fn (array $left, array $right): int => count($right) <=> count($left));
        $items = '';
        foreach (array_slice($files, 0, self::TOP, true) as $file => $entries) {
            $items .= '<li>' . $this->text->link($file, $site->filePage($file), 'mono')
                . '<span class="usage-kind">' . $this->text->plural(count($entries), 'statement') . '</span></li>';
        }

        return $this->route(
            'Files',
            ReportSite::FILES,
            count($site->files()),
            'file',
            'The statements written in each file, function by function.',
            $items
        );
    }

    /**
     * The route by what a statement does.
     */
    public function kindRoute(ReportSite $site): string
    {
        $chips = '';
        foreach ($site->statistics()->byKind() as $kind => $count) {
            $chips .= '<li>' . $this->text->chipLink(strtoupper($kind), ReportSite::STATEMENTS . '?kind=' . rawurlencode($kind), $this->palette->kind($kind))
                . '<span class="usage-kind">' . $this->text->plural($count, 'statement') . '</span></li>';
        }

        return $this->route(
            'Statements',
            ReportSite::STATEMENTS,
            $site->statistics()->statements(),
            'statement',
            'Every statement, to narrow down by what it does, how far the analysis got and what was reported.',
            $chips
        );
    }

    /**
     * One route, as a card: where it leads, what it is for, the entries most worth starting from, and the way to all of them.
     */
    public function route(string $label, string $href, int $count, string $noun, string $hint, string $items): string
    {
        return '<section class="card"><h2>' . $this->text->link($label, $href) . $this->text->count($count) . '</h2>'
            . '<p class="card-description">' . $this->text->escape($hint) . '</p>'
            . ($items === '' ? '<p class="empty-inline">Nothing here.</p>' : '<ul class="usage-list">' . $items . '</ul>')
            . '<p class="card-more">' . $this->text->link('All ' . $this->text->plural($count, $noun), $href) . '</p></section>';
    }

    /**
     * What the analysis flagged, by rule and by the functions it flagged most.
     */
    public function attention(ReportSite $site): string
    {
        $counts = $site->index()->byRule();
        if ($counts === []) {
            return '<h2 id="attention">Needs attention</h2><p class="lede">Nothing was reported about any statement.</p>';
        }
        $rows = '';
        foreach ($counts as $value => $entries) {
            $rule = FindingRule::from($value);
            $rows .= '<tr><td class="tight">' . $this->text->link($value, ReportSite::FINDINGS . '#rule-' . $value, 'mono') . '</td>'
                . '<td class="tight">' . $this->text->chip($rule->severity()->value, $this->palette->severity($rule->severity())) . '</td>'
                . '<td class="num">' . $this->text->number(count($entries)) . '</td>'
                . '<td>' . $this->text->escape($rule->describe()) . '</td></tr>';
        }
        $spots = '';
        foreach (array_slice($site->index()->hotspots(), 0, self::TOP) as $spot) {
            $spots .= '<li>' . $this->text->link(Scope::of($spot['function'])->display(), ReportSite::FINDINGS . '#hotspots', 'mono')
                . '<span class="usage-kind">' . $this->text->escape($spot['file'])
                . ($spot['high'] > 0 ? ' · ' . $this->text->number($spot['high']) . ' high' : '')
                . ($spot['medium'] > 0 ? ' · ' . $this->text->number($spot['medium']) . ' medium' : '') . '</span></li>';
        }

        return '<h2 id="attention">Needs attention' . $this->text->count($site->statistics()->findings(), 'finding') . '</h2>'
            . '<div class="split"><div class="table-wrap"><table><thead><tr><th scope="col">Rule</th><th scope="col" class="tight">Severity</th>'
            . '<th scope="col" class="num">Statements</th><th scope="col">What it reports</th></tr></thead><tbody>' . $rows . '</tbody></table></div>'
            . ($spots === '' ? '' : '<section class="aside"><h3>Functions issuing flagged statements</h3>'
                . '<ul class="usage-list">' . $spots . '</ul><p class="more">'
                . $this->text->link('Every flagged function', ReportSite::FINDINGS . '#hotspots') . '</p></section>')
            . '</div>';
    }

    /**
     * How far the analysis got, as one meter whose every segment leads to the statements it counts.
     */
    public function coverage(ReportSite $site): string
    {
        $stats = $site->statistics();
        $total = $stats->statements();
        $segments = '';
        $legend = '';
        foreach ($stats->byResolution() as $value => $count) {
            $resolution = Resolution::from($value);
            $role = $this->palette->resolution($resolution);
            $href = ReportSite::STATEMENTS . '?resolution=' . rawurlencode($value);
            if ($count > 0) {
                $segments .= '<a class="meter-part ' . $this->text->escape($this->palette->bar($role)) . '" style="--dd-part:' . $this->text->escape($this->text->percent($count, $total))
                    . '" href="' . $this->text->escape($href) . '" title="' . $this->text->escape($value . ': ' . $resolution->describe()) . '"></a>';
            }
            $legend .= '<li>' . $this->text->chipLink($value, $href, $role, $resolution->describe())
                . '<span class="meter-legend-count">' . $this->text->number($count) . '</span>'
                . '<span class="meter-legend-description">' . $this->text->escape($resolution->describe()) . '</span></li>';
        }
        $open = $stats->open();

        return '<h2 id="coverage">How far the analysis got</h2>'
            . '<div class="meter meter-lg">' . $segments . '</div><ul class="legend">' . $legend . '</ul>'
            . '<p class="muted">' . ($open === 0
                ? 'Every search closed: the statements listed are all of them.'
                : $this->text->link($this->text->plural($open, 'statement'), ReportSite::STATEMENTS . '?open=open')
                    . ' are lower bounds: a dependency, a cycle or a budget stopped the search, so the call may issue more than is listed.') . '</p>';
    }

    /**
     * The files that could not be read at all.
     */
    public function problems(ReportSite $site): string
    {
        $problems = $site->catalog()->problems();
        if ($problems === []) {
            return '';
        }
        $rows = '';
        foreach ($problems as $problem) {
            $rows .= '<tr><td><code>' . $this->text->escape($problem->file) . '</code></td>'
                . '<td>' . $this->text->escape($problem->message) . '</td></tr>';
        }

        return '<h2 id="problems">Not read' . $this->text->count(count($problems), 'file') . '</h2>'
            . '<p class="lede">These files could not be parsed, so nothing in them was catalogued.</p>'
            . '<div class="table-wrap"><table><thead><tr><th scope="col">File</th><th scope="col">Why</th></tr></thead><tbody>'
            . $rows . '</tbody></table></div>';
    }
}
