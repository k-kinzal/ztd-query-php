<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html;

use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Catalog\FindingRule;
use SqlCatalog\Catalog\Resolution;
use SqlCatalog\Catalog\Severity;

/**
 * The page a reader opens first: what was found, and how far the analysis got.
 *
 * @visibility root
 */
final class OverviewPage
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
     * The body of the overview.
     */
    public function render(ReportSite $site, Catalog $catalog, CatalogStatistics $stats): string
    {
        return '<h1>SQL catalog</h1>'
            . '<p class="lede">Every statement this source can issue, read back from the calls that receive it. '
            . 'Where the text could not be pinned down, the report says which dependency stopped the search '
            . 'rather than leaving the statement simply unknown.</p>'
            . $this->cards($stats)
            . $this->resolutions($stats)
            . $this->kinds($stats)
            . $this->rules($site, $stats)
            . $this->tables($site, $stats)
            . $this->files($site, $stats)
            . $this->problems($catalog);
    }

    /**
     * The four counts a reader looks at first.
     */
    public function cards(CatalogStatistics $stats): string
    {
        $cards = [
            ['', $stats->statements(), 'statements'],
            ['card-ok', $stats->resolved(), 'fully resolved'],
            ['card-warn', $stats->open(), 'search left open'],
            ['card-danger', $stats->atLeast(Severity::High), 'statements needing attention'],
        ];
        $written = '';
        foreach ($cards as [$role, $figure, $label]) {
            $written .= '<div class="card ' . $role . '"><p class="card-figure">' . $this->text->number($figure)
                . '</p><p class="card-label">' . $this->text->escape($label) . '</p></div>';
        }

        return '<div class="cards">' . $written . '</div>';
    }

    /**
     * How far the analyzer got, and why it got no further.
     */
    public function resolutions(CatalogStatistics $stats): string
    {
        $total = $stats->statements();
        $rows = '';
        foreach ($stats->byResolution() as $value => $count) {
            $resolution = Resolution::from($value);
            $rows .= '<tr><td class="tight">'
                . $this->text->chip($value, $this->card->resolutionRole($resolution)) . '</td>'
                . '<td class="num">' . $this->text->number($count) . '</td>'
                . '<td class="num">' . $this->text->escape($this->text->percent($count, $total)) . '</td>'
                . '<td>' . $this->text->escape($resolution->describe())
                . $this->text->bar($count, $total, $this->barRole($this->card->resolutionRole($resolution)))
                . '</td></tr>';
        }

        return '<h2>Resolution</h2><div class="table-wrap"><table><thead><tr>'
            . '<th class="tight">Resolution</th><th class="num">Statements</th><th class="num">Share</th>'
            . '<th>What it means</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    /**
     * What the statements do.
     */
    public function kinds(CatalogStatistics $stats): string
    {
        $total = $stats->statements();
        $rows = '';
        foreach ($stats->byKind() as $value => $count) {
            $role = $this->card->kindRole($value);
            $rows .= '<tr><td class="tight">' . $this->text->chip(strtoupper($value), $role) . '</td>'
                . '<td class="num">' . $this->text->number($count) . '</td>'
                . '<td class="num">' . $this->text->escape($this->text->percent($count, $total)) . '</td>'
                . '<td>' . $this->text->bar($count, $total, 'bar-' . substr($role, 2)) . '</td></tr>';
        }

        return '<h2>Statement kinds</h2><div class="table-wrap"><table><thead><tr>'
            . '<th class="tight">Kind</th><th class="num">Statements</th><th class="num">Share</th>'
            . '<th>&nbsp;</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    /**
     * What is worth reporting, by rule.
     */
    public function rules(ReportSite $site, CatalogStatistics $stats): string
    {
        $counts = $stats->byRule();
        if ($counts === []) {
            return '<h2>Findings</h2><p class="lede">Nothing was reported about any statement.</p>';
        }
        $rows = '';
        foreach ($counts as $value => $count) {
            $rule = FindingRule::from($value);
            $rows .= '<tr><td class="tight"><a href="' . $this->text->escape(ReportSite::FINDINGS . '#rule-' . $value) . '">'
                . '<code>' . $this->text->escape($value) . '</code></a></td>'
                . '<td class="tight">' . $this->text->chip($rule->severity()->value, $this->card->severityRole($rule->severity())) . '</td>'
                . '<td class="num">' . $this->text->number($count) . '</td>'
                . '<td>' . $this->text->escape($rule->describe()) . '</td></tr>';
        }

        return '<h2>Findings <span class="count">' . $this->text->escape($this->text->plural($stats->findings(), 'finding'))
            . '</span></h2><div class="table-wrap"><table><thead><tr><th>Rule</th><th class="tight">Severity</th>'
            . '<th class="num">Count</th><th>What it reports</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    /**
     * The tables the statements name, most used first.
     */
    public function tables(ReportSite $site, CatalogStatistics $stats): string
    {
        $tables = $stats->tables();
        if ($tables === []) {
            return '';
        }
        $rows = '';
        foreach (array_slice($tables, 0, 12) as $table) {
            $rows .= '<tr><td><a href="' . $this->text->escape(ReportSite::TABLES . '#table-' . $this->text->slug($table['name']))
                . '"><code>' . $this->text->escape($table['name']) . '</code></a></td>'
                . '<td class="num">' . $this->text->number($table['reads']) . '</td>'
                . '<td class="num">' . $this->text->number($table['writes']) . '</td></tr>';
        }

        return '<h2>Tables <span class="count">' . $this->text->escape($this->text->plural(count($tables), 'table'))
            . '</span></h2><p class="lede">The twelve the statements name most. '
            . '<a href="' . $this->text->escape(ReportSite::TABLES) . '">See every table</a>.</p>'
            . '<div class="table-wrap"><table><thead><tr><th>Table</th><th class="num">Read by</th>'
            . '<th class="num">Written by</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    /**
     * Every file the statements were found in.
     */
    public function files(ReportSite $site, CatalogStatistics $stats): string
    {
        $files = $stats->files();
        if ($files === []) {
            return '';
        }
        $rows = '';
        foreach ($files as $file => $row) {
            $rows .= '<tr><td><a href="' . $this->text->escape($site->fileUrl($file)) . '"><code>'
                . $this->text->escape($file) . '</code></a></td>'
                . '<td class="num">' . $this->text->number($row['statements']) . '</td>'
                . '<td class="num">' . ($row['open'] === 0 ? '<span class="none">0</span>' : $this->text->number($row['open'])) . '</td>'
                . '<td class="num">' . ($row['findings'] === 0 ? '<span class="none">0</span>' : $this->text->number($row['findings'])) . '</td></tr>';
        }

        return '<h2>Files <span class="count">' . $this->text->escape($this->text->plural(count($files), 'file'))
            . '</span></h2><div class="table-wrap"><table><thead><tr><th>File</th><th class="num">Statements</th>'
            . '<th class="num">Left open</th><th class="num">Findings</th></tr></thead><tbody>'
            . $rows . '</tbody></table></div>';
    }

    /**
     * The files that could not be read at all.
     */
    public function problems(Catalog $catalog): string
    {
        if ($catalog->problems() === []) {
            return '';
        }
        $rows = '';
        foreach ($catalog->sorted()->problems() as $problem) {
            $rows .= '<tr><td><code>' . $this->text->escape($problem->file) . '</code></td>'
                . '<td>' . $this->text->escape($problem->message) . '</td></tr>';
        }

        return '<h2>Not read <span class="count">'
            . $this->text->escape($this->text->plural(count($catalog->problems()), 'file'))
            . '</span></h2><p class="lede">These files could not be parsed, so nothing in them was catalogued.</p>'
            . '<div class="table-wrap"><table><thead><tr><th>File</th><th>Why</th></tr></thead><tbody>'
            . $rows . '</tbody></table></div>';
    }

    /**
     * The bar tint that goes with a chip role.
     */
    public function barRole(string $chipRole): string
    {
        return 'bar-' . substr($chipRole, 2);
    }
}
