<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html;

use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Catalog\CatalogEntry;

/**
 * The pages the report is split across, and where each statement sits.
 *
 * A catalog of a real application runs to thousands of statements, which is
 * more than one document can carry and more than a reader can scroll. The
 * statements are split across pages by the file they are written in, and a
 * file's statements are never split: a file with more statements than a page
 * holds gets a page to itself. Every page therefore reads as whole files and
 * the navigation can name them. Once the split is decided, every statement has
 * an address, which is what lets the overview, the tables and the findings link
 * to statements rather than repeat them.
 *
 * @visibility root
 */
final class ReportSite
{
    /**
     * How many statements a page holds before the next file starts a new one.
     */
    public const PER_PAGE = 40;

    /**
     * The page a reader opens first.
     */
    public const INDEX = 'index.html';

    /**
     * The page listing every table the catalog names.
     */
    public const TABLES = 'tables.html';

    /**
     * The page listing every finding.
     */
    public const FINDINGS = 'findings.html';

    /**
     * @var list<array<string, list<CatalogEntry>>>
     */
    private array $pages;

    /**
     * @var array<string, int>
     */
    private array $located = [];

    /**
     * @var array<string, int>
     */
    private array $filePages = [];

    private HtmlText $text;

    /**
     * Splits a catalog into the pages it is read across.
     *
     * @param int $perPage How many statements a page holds before the next file starts a new one
     */
    public function __construct(Catalog $catalog, int $perPage = self::PER_PAGE, ?HtmlText $text = null)
    {
        $this->text = $text ?? new HtmlText();
        $this->pages = $this->paginate($this->groupByFile($catalog), max(1, $perPage));

        foreach ($this->pages as $index => $page) {
            foreach ($page as $file => $entries) {
                $this->filePages[$file] = $index + 1;
                foreach ($entries as $entry) {
                    $this->located[$entry->id] = $index + 1;
                }
            }
        }
    }

    /**
     * The statements of each file, in the order the report lists them.
     *
     * @return array<string, list<CatalogEntry>>
     */
    public function groupByFile(Catalog $catalog): array
    {
        $grouped = [];
        foreach ($catalog->sorted() as $entry) {
            $grouped[$entry->site->file][] = $entry;
        }

        return $grouped;
    }

    /**
     * The file groups packed into pages.
     *
     * @param array<string, list<CatalogEntry>> $grouped
     * @return list<array<string, list<CatalogEntry>>>
     */
    public function paginate(array $grouped, int $perPage): array
    {
        $pages = [];
        $page = [];
        $held = 0;
        foreach ($grouped as $file => $entries) {
            if ($page !== [] && $held + count($entries) > $perPage) {
                $pages[] = $page;
                $page = [];
                $held = 0;
            }
            $page[$file] = $entries;
            $held += count($entries);
        }
        if ($page !== []) {
            $pages[] = $page;
        }

        return $pages === [] ? [[]] : $pages;
    }

    /**
     * The pages, each holding the files it lists.
     *
     * @return list<array<string, list<CatalogEntry>>>
     */
    public function pages(): array
    {
        return $this->pages;
    }

    /**
     * How many pages the statements are split across.
     */
    public function pageCount(): int
    {
        return count($this->pages);
    }

    /**
     * The name the statements of that page are written under.
     */
    public function pageName(int $number): string
    {
        return 'statements/page-' . $number . '.html';
    }

    /**
     * The address of one statement, relative to the root of the report.
     */
    public function urlOf(string $id): string
    {
        $page = $this->located[$id] ?? null;

        return $page === null ? self::INDEX : $this->pageName($page) . '#' . $id;
    }

    /**
     * The address of one file's statements, relative to the root of the report.
     */
    public function fileUrl(string $file): string
    {
        $page = $this->filePages[$file] ?? null;

        return $page === null ? self::INDEX : $this->pageName($page) . '#' . $this->fileAnchor($file);
    }

    /**
     * The identifier a file's statements are grouped under.
     */
    public function fileAnchor(string $file): string
    {
        return 'file-' . $this->text->slug($file);
    }

    /**
     * Every file the report lists, with how many statements it holds.
     *
     * @return array<string, int>
     */
    public function files(): array
    {
        $files = [];
        foreach ($this->pages as $page) {
            foreach ($page as $file => $entries) {
                $files[$file] = count($entries);
            }
        }

        return $files;
    }

    /**
     * What a link from that page has to be written through to reach the root.
     */
    public function prefixOf(string $page): string
    {
        return str_repeat('../', substr_count($page, '/'));
    }
}
