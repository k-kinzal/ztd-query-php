<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html;

use SqlCatalog\Catalog\CatalogEntry;

/**
 * The index the search box reads, written as a script beside the pages.
 *
 * Splitting a catalog across pages is what makes it readable and what makes a
 * statement hard to find, so the split comes with an index of every statement
 * and the page it is on. The index is a script rather than data fetched at
 * runtime, so the report still works opened from a file.
 *
 * @visibility root
 */
final class SearchIndex
{
    /**
     * How much of a statement the index carries.
     */
    public const LENGTH = 240;

    private HtmlText $text;

    private Palette $palette;

    /**
     * Wires the index to the rendering the search results are written with.
     */
    public function __construct(?HtmlText $text = null, ?Palette $palette = null)
    {
        $this->text = $text ?? new HtmlText();
        $this->palette = $palette ?? new Palette();
    }

    /**
     * The index as the script the pages load.
     */
    public function render(ReportSite $site): string
    {
        $entries = [];
        foreach ($site->index()->entries() as $entry) {
            $entries[] = $this->entryToArray($site, $entry);
        }
        $encoded = json_encode($entries, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return 'window.__CATALOG_INDEX__ = ' . ($encoded === false ? '[]' : $encoded) . ";\n";
    }

    /**
     * One statement as the search reads it.
     *
     * @return array{q: string, w: string, f: string, t: string, u: string, k: string, g: string, r: string, c: string}
     */
    public function entryToArray(ReportSite $site, CatalogEntry $entry): array
    {
        $resolution = $entry->resolution();

        return [
            'q' => $this->text->truncate($entry->sql(), self::LENGTH),
            'w' => $entry->site->display(),
            'f' => $entry->site->function,
            't' => implode(' ', $entry->tables),
            'u' => $site->statementPage($entry->id),
            'k' => strtoupper($entry->kind->value),
            'g' => $this->palette->kindGroup($entry->kind->value),
            'r' => $resolution->value,
            'c' => substr($this->palette->resolution($resolution), 2),
        ];
    }
}
