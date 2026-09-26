<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html;

use SqlCatalog\Core\Catalog\CatalogEntry;
use SqlCatalog\Core\Catalog\Resolution;
use SqlCatalog\Core\Catalog\Severity;

/**
 * A listing of statements that a reader can narrow down on the page.
 *
 * Every listing in the report is the same component, doc-ui's listing with
 * its facets: the rows, and above them the facets the rows can be narrowed by — what the statements do, how
 * far the analysis got, and how much attention they need — each showing how
 * many rows it would keep. A facet that every row shares is not offered,
 * since choosing it would change nothing.
 *
 * @visibility root
 */
final class StatementList
{
    private HtmlText $text;

    private StatementRow $row;

    private Palette $palette;

    /**
     * Wires the listing to the row and text rendering it is written with.
     */
    public function __construct(?HtmlText $text = null, ?StatementRow $row = null, ?Palette $palette = null)
    {
        $this->text = $text ?? new HtmlText();
        $this->row = $row ?? new StatementRow($this->text);
        $this->palette = $palette ?? new Palette();
    }

    /**
     * The rows of a listing.
     *
     * @param list<CatalogEntry> $entries
     * @param list<string> $omit The facts the listing already states, out of `function`, `file` and `tables`
     */
    public function rows(ReportSite $site, string $page, array $entries, array $omit = []): string
    {
        if ($entries === []) {
            return '<p class="empty-inline">No statement here.</p>';
        }
        $rows = '';
        foreach ($entries as $entry) {
            $rows .= $this->row->render($site, $page, $entry, $omit);
        }

        return '<ol class="rows">' . $rows . '</ol>';
    }

    /**
     * The facets a listing can be narrowed by, with a search over its rows.
     *
     * @param list<CatalogEntry> $entries
     */
    public function facets(array $entries): string
    {
        $kinds = [];
        $resolutions = [];
        $severities = [];
        foreach ($entries as $entry) {
            $kinds[$entry->kind->value] = ($kinds[$entry->kind->value] ?? 0) + 1;
            $resolutions[$entry->resolution()->value] = ($resolutions[$entry->resolution()->value] ?? 0) + 1;
            if ($entry->findings !== []) {
                $severities[$entry->severity()->value] = ($severities[$entry->severity()->value] ?? 0) + 1;
            }
        }
        arsort($kinds);
        arsort($resolutions);
        arsort($severities);

        return '<div class="facets" role="group" aria-label="Narrow the listing">'
            . '<input type="search" name="narrow" class="input facet-search" placeholder="Narrow by text…" aria-label="Narrow by text" autocomplete="off" spellcheck="false">'
            . $this->group('kind', $kinds, fn (string $kind): string => $this->palette->kind($kind), 'strtoupper')
            . $this->group('resolution', $resolutions, fn (string $value): string => $this->palette->resolution(Resolution::from($value)))
            . $this->group('severity', $severities, fn (string $value): string => $this->palette->severity(Severity::from($value)))
            . '<span class="facet-shown" aria-live="polite"></span>'
            . '<button type="button" class="btn facet-clear" hidden>Clear</button>'
            . '</div>';
    }

    /**
     * One facet, as a chip per value with how many rows it keeps.
     *
     * @param array<string, int> $counts
     * @param callable(string): string $role
     * @param callable(string): string|null $label
     */
    public function group(string $facet, array $counts, callable $role, ?callable $label = null): string
    {
        if (count($counts) < 2) {
            return '';
        }
        $chips = '';
        foreach ($counts as $value => $count) {
            $chips .= '<button type="button" class="chip facet ' . $this->text->escape($role($value)) . '" data-facet="'
                . $this->text->escape($facet) . '" data-value="' . $this->text->escape($value) . '" aria-pressed="false">'
                . $this->text->escape($label === null ? $value : $label($value))
                . '<span class="facet-count">' . $this->text->number($count) . '</span></button>';
        }

        return '<span class="facet-group">' . $chips . '</span>';
    }
}
