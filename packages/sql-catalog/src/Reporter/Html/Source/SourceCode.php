<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html\Source;

use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\ReportSite;

/**
 * The analyzed PHP source, with numbered lines and the database calls marked.
 *
 * @visibility root
 */
final class SourceCode
{
    /**
     * The lines shown before and after a call on its statement page.
     */
    public const CONTEXT = 8;

    private HtmlText $text;

    /**
     * Wires source rendering to the escaping used throughout the report.
     */
    public function __construct(?HtmlText $text = null)
    {
        $this->text = $text ?? new HtmlText();
    }

    /**
     * A call's surrounding code, linked to the complete file at the same line.
     */
    public function excerpt(ReportSite $site, CatalogEntry $entry): string
    {
        $source = $site->catalog()->source($entry->site->file);
        if ($source === null) {
            return '';
        }
        $first = max(1, $entry->site->line - self::CONTEXT);
        $last = $entry->site->line + self::CONTEXT;
        $lines = array_slice($this->split($source), $first - 1, $last - $first + 1);
        $file = '../' . $site->filePage($entry->site->file);

        return '<section><h2 id="source">Source code</h2><p class="muted">'
            . $this->text->escape($entry->site->display()) . ' · '
            . $this->text->link('View full source', $file . '#L' . $entry->site->line) . '</p>'
            . $this->lines($lines, $first, [$entry->site->line], $file) . '</section>';
    }

    /**
     * A file's complete source, including files that could not be parsed.
     */
    public function file(ReportSite $site, string $file): string
    {
        $source = $site->catalog()->source($file);
        if ($source === null) {
            return '';
        }
        $calls = [];
        foreach ($site->index()->byFile()[$file] ?? [] as $entry) {
            $calls[] = $entry->site->line;
        }

        return '<section><h2 id="source">Source code</h2><p class="muted">Source captured during analysis. Highlighted lines issue database calls.</p>'
            . $this->lines($this->split($source), 1, $calls) . '</section>';
    }

    /**
     * Source lines with their original indentation, escaped before embedding.
     *
     * @param list<string> $lines
     * @param list<int> $calls The line numbers to highlight
     * @param string $target The full source page, or empty for links within this page
     */
    public function lines(array $lines, int $first, array $calls, string $target = ''): string
    {
        $written = '';
        $highlighted = array_fill_keys($calls, true);
        foreach ($lines as $offset => $line) {
            $number = $first + $offset;
            $written .= '<span class="source-line' . (isset($highlighted[$number]) ? ' source-call' : '') . '" id="L' . $number . '">'
                . '<a class="source-number" href="' . $this->text->escape($target . '#L' . $number) . '" aria-label="Line ' . $number . '">' . $number . '</a>'
                . '<span class="source-text">' . $this->text->escape($line) . '</span></span>';
        }

        return '<pre class="code source-code" tabindex="0" aria-label="PHP source code"><code>' . $written . '</code></pre>';
    }

    /**
     * Lines split using PHP's newline conventions, retaining a trailing empty line.
     *
     * @return list<string>
     */
    public function split(string $source): array
    {
        return explode("\n", str_replace(["\r\n", "\r"], "\n", $source));
    }
}
