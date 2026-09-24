<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html\Page;

use SqlCatalog\Catalog\FindingRule;
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\Palette;
use SqlCatalog\Reporter\Html\ReportSite;
use SqlCatalog\Reporter\Html\Scope;
use SqlCatalog\Reporter\Html\StatementList;

/**
 * Everything worth reporting: where to look first, then every finding under its rule.
 *
 * The question behind a finding is usually about code rather than about one
 * statement — which functions build SQL out of values they do not control —
 * so the page opens with the functions ranked by what was reported on them,
 * and only then lists the statements rule by rule.
 *
 * @visibility root
 */
final class FindingPage
{
    private HtmlText $text;

    private StatementList $list;

    private Palette $palette;

    /**
     * Wires the page to the rendering it is written with.
     */
    public function __construct(?HtmlText $text = null, ?StatementList $list = null, ?Palette $palette = null)
    {
        $this->text = $text ?? new HtmlText();
        $this->list = $list ?? new StatementList($this->text);
        $this->palette = $palette ?? new Palette();
    }

    /**
     * The body of the findings page.
     */
    public function render(ReportSite $site): string
    {
        $grouped = $site->index()->byRule();
        if ($grouped === []) {
            return '<h1>Findings</h1><p class="lede">Nothing was reported about any statement.</p>';
        }
        $sections = '';
        foreach ($grouped as $value => $entries) {
            $sections .= $this->section($site, FindingRule::from($value), $entries);
        }

        return '<h1>Findings' . $this->text->count($site->statistics()->findings(), 'finding') . '</h1>'
            . '<p class="lede">A finding is a judgement about a statement the analyzer read: a value spliced into the text, '
            . 'a value that comes from outside the program, or a search that stopped short. What stopped the analysis is reported too, '
            . 'so a gap in the catalog is never silent.</p>'
            . $this->hotspots($site)
            . $sections;
    }

    /**
     * The functions issuing flagged statements, worst first.
     */
    public function hotspots(ReportSite $site): string
    {
        $rows = '';
        foreach ($site->index()->hotspots() as $spot) {
            $entry = $site->index()->byFunction()[$spot['function']][0];
            $rows .= '<tr><td>' . $this->text->link(Scope::of($spot['function'])->display(), $site->functionUrl($entry), 'mono') . '</td>'
                . '<td>' . $this->text->link($spot['file'], $site->filePage($spot['file']), 'muted') . '</td>'
                . '<td class="num">' . ($spot['high'] === 0 ? '<span class="none">0</span>' : $this->text->number($spot['high'])) . '</td>'
                . '<td class="num">' . ($spot['medium'] === 0 ? '<span class="none">0</span>' : $this->text->number($spot['medium'])) . '</td></tr>';
        }
        if ($rows === '') {
            return '';
        }

        return '<h2 id="hotspots">Where to look first</h2>'
            . '<p class="lede">The functions issuing statements with a high or medium finding: SQL built from external input, '
            . 'or from values spliced into the text rather than bound. A function high on this list is one to read before trusting its queries.</p>'
            . '<div class="table-wrap"><table class="sortable" data-dd-sortable><thead><tr><th scope="col" data-dd-sort="text">Function</th><th scope="col" data-dd-sort="text">File</th>'
            . '<th scope="col" class="num" data-dd-sort="number">High</th><th scope="col" class="num" data-dd-sort="number">Medium</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    /**
     * One rule, with everything it reported.
     *
     * @param list<\SqlCatalog\Catalog\CatalogEntry> $entries
     */
    public function section(ReportSite $site, FindingRule $rule, array $entries): string
    {
        $id = 'rule-' . $rule->value;

        return '<section class="group" id="' . $this->text->escape($id) . '"><h2><code>' . $this->text->escape($rule->value) . '</code>'
            . $this->text->chip($rule->severity()->value, $this->palette->severity($rule->severity()))
            . $this->text->count(count($entries), 'statement')
            . '<a class="anchor" href="#' . $this->text->escape($id) . '">#</a></h2>'
            . '<p class="lede">' . $this->text->escape($rule->describe()) . '</p>'
            . $this->list->rows($site, ReportSite::FINDINGS, $entries) . '</section>';
    }

    /**
     * The sections of the page, for the navigation.
     *
     * @return list<array{string, string}>
     */
    public function anchors(ReportSite $site): array
    {
        $anchors = $site->index()->hotspots() === [] ? [] : [['Where to look first', 'hotspots']];
        foreach (array_keys($site->index()->byRule()) as $rule) {
            $anchors[] = [$rule, 'rule-' . $rule];
        }

        return $anchors;
    }
}
