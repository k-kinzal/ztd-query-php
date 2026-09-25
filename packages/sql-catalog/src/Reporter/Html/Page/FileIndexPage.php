<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html\Page;

use SqlCatalog\Core\Catalog\CatalogEntry;
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\ReportSite;

/**
 * Every file the statements are written in, under the directory that holds it.
 *
 * A codebase without classes is still organised, by directory and file, and
 * that is the route through it. The listing is the tree of the source as far
 * as the statements reach, with what was found in each file beside it.
 *
 * @visibility root
 */
final class FileIndexPage
{
    private HtmlText $text;

    /**
     * Wires the page to the escaping it writes through.
     */
    public function __construct(?HtmlText $text = null)
    {
        $this->text = $text ?? new HtmlText();
    }

    /**
     * The body of the files listing.
     */
    public function render(ReportSite $site): string
    {
        $directories = $site->index()->byDirectory();
        if ($directories === []) {
            return '<h1>Files</h1><p class="lede">No statement was found.</p>';
        }
        $sections = '';
        foreach ($directories as $directory => $files) {
            $sections .= $this->section($site, $directory, $files);
        }

        return '<h1>Files' . $this->text->count(count($site->files()), 'file') . '</h1>'
            . '<p class="lede">Every file a statement is written in, by directory. Open a file to read its statements function by function.</p>'
            . '<input type="search" name="filter" class="input input-block" data-filter-rows placeholder="Narrow by file name…" aria-label="Narrow by file name" autocomplete="off" spellcheck="false">'
            . $sections;
    }

    /**
     * One directory, with the files in it.
     *
     * @param list<string> $files
     */
    public function section(ReportSite $site, string $directory, array $files): string
    {
        $index = $site->index();
        $byFile = $index->byFile();
        $rows = '';
        foreach ($files as $file) {
            $entries = $byFile[$file] ?? [];
            $rows .= $this->row($site, $file, $entries);
        }
        $label = $directory === '' ? '(root)' : $directory . '/';

        return '<section class="group"><h2 id="' . $this->text->escape('dir-' . $this->text->slug($label)) . '"><code>' . $this->text->escape($label) . '</code>'
            . $this->text->count(count($files), 'file') . '</h2>'
            . '<div class="table-wrap"><table class="sortable filter-target" data-dd-sortable><thead><tr><th scope="col" data-dd-sort="text">File</th>'
            . '<th scope="col" class="num" data-dd-sort="number">Statements</th><th scope="col" class="num" data-dd-sort="number">Functions</th>'
            . '<th scope="col" class="num" data-dd-sort="number">Tables</th><th scope="col" class="num" data-dd-sort="number">Attention</th></tr></thead><tbody>'
            . $rows . '</tbody></table></div></section>';
    }

    /**
     * One file as a row.
     *
     * @param list<CatalogEntry> $entries
     */
    public function row(ReportSite $site, string $file, array $entries): string
    {
        $index = $site->index();
        $usage = $index->usage($entries);
        $slash = strrpos($file, '/');
        $cells = [count($entries), count($index->functionsOf($entries)), count($index->tablesOf($entries)), $usage['attention']];
        $written = '';
        foreach ($cells as $cell) {
            $written .= '<td class="num">' . ($cell === 0 ? '<span class="none">0</span>' : $this->text->number($cell)) . '</td>';
        }

        return '<tr><td>' . $this->text->link($slash === false ? $file : substr($file, $slash + 1), $site->filePage($file), 'mono') . '</td>' . $written . '</tr>';
    }

    /**
     * The sections of the page, for the navigation.
     *
     * @return list<array{string, string}>
     */
    public function anchors(ReportSite $site): array
    {
        $anchors = [];
        foreach (array_keys($site->index()->byDirectory()) as $directory) {
            $label = $directory === '' ? '(root)' : $directory . '/';
            $anchors[] = [$label, 'dir-' . $this->text->slug($label)];
        }

        return $anchors;
    }
}
