<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter;

use Override;
use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Catalog\CatalogEntry;

/**
 * Writes the catalog as one self-contained HTML page.
 *
 * @visibility root
 */
final class HtmlReporter implements ReporterInterface
{
    /**
     * The name the artifact is written under.
     */
    public const FILE = 'index.html';

    /**
     * The name the command line selects this reporter by.
     */
    #[Override]
    public function name(): string
    {
        return 'html';
    }

    /**
     * What the reporter produces.
     */
    #[Override]
    public function description(): string
    {
        return 'a self-contained HTML page, for reading and sharing';
    }

    /**
     * The catalog rendered as one HTML page.
     */
    #[Override]
    public function render(Catalog $catalog): CatalogArtifacts
    {
        $rows = '';
        foreach ($catalog->sorted() as $entry) {
            $rows .= $this->row($entry);
        }

        $document = '<!DOCTYPE html>' . "\n"
            . '<html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>SQL catalog</title><style>' . $this->styles() . '</style></head><body>'
            . '<h1>SQL catalog</h1>'
            . $this->summary($catalog)
            . '<table><thead><tr><th>Statement</th><th>Where</th><th>Values</th><th>Findings</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table>'
            . $this->problems($catalog)
            . '</body></html>' . "\n";

        return CatalogArtifacts::one(self::FILE, $document);
    }

    /**
     * The counts shown above the table.
     */
    public function summary(Catalog $catalog): string
    {
        $exact = 0;
        $findings = 0;
        foreach ($catalog as $entry) {
            $exact += $entry->isExact() ? 1 : 0;
            $findings += count($entry->findings);
        }

        return sprintf(
            '<p class="summary">%d statement(s) &middot; %d fully resolved &middot; %d finding(s)</p>',
            $catalog->count(),
            $exact,
            $findings,
        );
    }

    /**
     * One statement rendered as a table row.
     */
    public function row(CatalogEntry $entry): string
    {
        return '<tr class="sev-' . $this->escape($entry->severity()->value) . '">'
            . '<td><span class="kind">' . $this->escape(strtoupper($entry->kind->value)) . '</span> '
            . '<span class="resolution ' . $this->escape($entry->resolution()->value) . '">'
            . $this->escape($entry->resolution()->value) . '</span>'
            . '<pre>' . $this->escape($entry->sql()) . '</pre>'
            . '<p class="tables">' . $this->escape(implode(', ', $entry->tables)) . '</p>'
            . $this->caveats($entry) . '</td>'
            . '<td><code>' . $this->escape($entry->site->display()) . '</code>'
            . '<p>' . $this->escape($entry->site->function) . '</p>'
            . '<p class="sink">' . $this->escape($entry->site->sink) . '</p></td>'
            . '<td>' . $this->values($entry) . '</td>'
            . '<td>' . $this->findings($entry) . '</td>'
            . '</tr>';
    }

    /**
     * What a reader has to know before trusting the statements listed for a call site.
     */
    public function caveats(CatalogEntry $entry): string
    {
        $caveats = [];
        if (!$entry->resolution()->isClosed()) {
            $caveats[] = 'the search did not close, so these may not be all of them';
        }
        if (!$entry->correlated) {
            $caveats[] = 'assembled from parts that vary independently, so some may be unreachable';
        }
        if (count($entry->through) > 1) {
            $caveats[] = 'read through ' . implode(' &rarr; ', array_map($this->escape(...), $entry->through));
        }

        return $caveats === [] ? '' : '<p class="caveat">' . implode('; ', $caveats) . '</p>';
    }

    /**
     * The bind parameters of one statement, rendered as a list.
     */
    public function values(CatalogEntry $entry): string
    {
        if ($entry->placeholders === []) {
            return '<span class="none">none</span>';
        }
        $items = '';
        foreach ($entry->placeholders as $placeholder) {
            $items .= '<li><code>' . $this->escape($placeholder->token) . '</code> '
                . $this->escape($placeholder->value?->display() ?? 'unbound') . '</li>';
        }

        return '<ul>' . $items . '</ul>';
    }

    /**
     * The findings of one statement, rendered as a list.
     */
    public function findings(CatalogEntry $entry): string
    {
        if ($entry->findings === []) {
            return '<span class="none">none</span>';
        }
        $items = '';
        foreach ($entry->findings as $finding) {
            $items .= '<li><span class="badge ' . $this->escape($finding->severity->value) . '">'
                . $this->escape($finding->severity->value) . '</span> '
                . $this->escape($finding->message) . '</li>';
        }

        return '<ul>' . $items . '</ul>';
    }

    /**
     * The files that could not be analyzed, rendered as a list.
     */
    public function problems(Catalog $catalog): string
    {
        if ($catalog->problems() === []) {
            return '';
        }
        $items = '';
        foreach ($catalog->sorted()->problems() as $problem) {
            $items .= '<li><code>' . $this->escape($problem->file) . '</code> ' . $this->escape($problem->message) . '</li>';
        }

        return '<h2>Not analyzed</h2><ul class="problems">' . $items . '</ul>';
    }

    /**
     * The stylesheet the page carries with it.
     */
    public function styles(): string
    {
        return 'body{font:14px/1.5 -apple-system,Segoe UI,Roboto,sans-serif;margin:2rem;color:#111}'
            . 'h1{font-size:1.4rem}.summary{color:#555}'
            . 'table{border-collapse:collapse;width:100%}'
            . 'th,td{border-top:1px solid #ddd;padding:.6rem;text-align:left;vertical-align:top}'
            . 'th{background:#f6f8fa;font-size:.8rem;text-transform:uppercase;letter-spacing:.04em}'
            . 'pre{margin:.3rem 0;white-space:pre-wrap;word-break:break-word;font-size:.85rem}'
            . 'code{font-size:.85rem}ul{margin:0;padding-left:1.1rem}'
            . '.kind{font-size:.7rem;font-weight:700;color:#57606a}'
            . '.tables,.sink{color:#57606a;font-size:.8rem;margin:.2rem 0}'
            . '.caveat{color:#9a6700;font-size:.8rem;margin:.2rem 0}'
            . '.resolution{font-size:.7rem;color:#57606a}'
            . '.resolution.external-input{color:#cf222e}'
            . '.resolution.incomplete,.resolution.incomplete-model{color:#bf8700}'
            . '.none{color:#8c959f}'
            . '.badge{display:inline-block;padding:0 .4rem;border-radius:.6rem;font-size:.7rem;color:#fff;background:#57606a}'
            . '.badge.high{background:#cf222e}.badge.medium{background:#bf8700}.badge.low{background:#0969da}'
            . 'tr.sev-high{background:#fff5f5}';
    }

    /**
     * Text made safe to place inside the document.
     */
    public function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
