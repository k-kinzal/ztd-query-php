<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html;

use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Catalog\Finding;
use SqlCatalog\Catalog\FindingRule;

/**
 * Everything worth reporting, gathered under the rule that reported it.
 *
 * @visibility root
 */
final class FindingPage
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
     * The body of the findings page.
     */
    public function render(ReportSite $site, Catalog $catalog, CatalogStatistics $stats): string
    {
        $counts = $stats->byRule();
        if ($counts === []) {
            return '<h1>Findings</h1><p class="lede">Nothing was reported about any statement.</p>';
        }
        $grouped = $this->group($catalog);

        $sections = '';
        foreach ($counts as $value => $count) {
            $rule = FindingRule::from($value);
            $sections .= $this->section($site, $rule, $count, $grouped[$value] ?? []);
        }

        return '<h1>Findings <span class="count">'
            . $this->text->escape($this->text->plural($stats->findings(), 'finding')) . '</span></h1>'
            . '<p class="lede">A finding is a judgement about a statement the analyzer read. '
            . 'What stopped the analysis is reported here too, so a gap in the catalog is never silent.</p>'
            . $sections;
    }

    /**
     * The statements each rule reported on, worst first.
     *
     * @return array<string, list<array{CatalogEntry, Finding}>>
     */
    public function group(Catalog $catalog): array
    {
        $grouped = [];
        foreach ($catalog->sorted() as $entry) {
            foreach ($entry->findings as $finding) {
                $grouped[$finding->rule->value][] = [$entry, $finding];
            }
        }

        return $grouped;
    }

    /**
     * One rule, with everything it reported.
     *
     * @param list<array{CatalogEntry, Finding}> $reported
     */
    public function section(ReportSite $site, FindingRule $rule, int $count, array $reported): string
    {
        $rows = '';
        foreach ($reported as [$entry, $finding]) {
            $rows .= $this->row($site, $entry, $finding);
        }

        return '<h2 id="rule-' . $this->text->escape($rule->value) . '"><code>'
            . $this->text->escape($rule->value) . '</code>'
            . $this->text->chip($rule->severity()->value, $this->card->severityRole($rule->severity()))
            . '<span class="count">' . $this->text->number($count) . '</span>'
            . '<a class="anchor" href="#rule-' . $this->text->escape($rule->value) . '">#</a></h2>'
            . '<p class="lede">' . $this->text->escape($rule->describe()) . '</p>'
            . '<div class="table-wrap"><table><thead><tr><th class="tight">Kind</th><th>Statement</th>'
            . '<th>What was found</th><th class="tight">Where</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    /**
     * One reported statement as a row.
     */
    public function row(ReportSite $site, CatalogEntry $entry, Finding $finding): string
    {
        return '<tr><td class="tight">'
            . $this->text->chip(strtoupper($entry->kind->value), $this->card->kindRole($entry->kind->value)) . '</td>'
            . '<td><a class="stmt-link" href="' . $this->text->escape($site->urlOf($entry->id)) . '"><code>'
            . $this->text->escape($this->text->truncate($entry->sql(), 90)) . '</code></a></td>'
            . '<td>' . $this->text->escape($finding->message) . '</td>'
            . '<td class="tight"><span class="stmt-site">' . $this->text->escape($entry->site->display())
            . '</span></td></tr>';
    }
}
