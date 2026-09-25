<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html;

use SqlCatalog\Core\Catalog\Catalog;
use SqlCatalog\Core\Catalog\CatalogEntry;

/**
 * The pages the report is made of, and the address of everything on them.
 *
 * A reader reaches a statement by a route — the table it names, the class or
 * file it is written in, the finding reported on it — and each route is a
 * page of its own. Once every table, class, file and statement has an
 * address, any page can point at any other rather than repeating it, which
 * is what lets a statement be found from wherever the reader started.
 *
 * @visibility root
 */
final class ReportSite
{
    /**
     * The page a reader opens first.
     */
    public const INDEX = 'index.html';

    /**
     * The page listing every statement, for narrowing down.
     */
    public const STATEMENTS = 'statements.html';

    /**
     * The page listing every table the catalog names.
     */
    public const TABLES = 'tables.html';

    /**
     * The page listing every namespace, with its classes and functions.
     */
    public const NAMESPACES = 'namespaces.html';

    /**
     * The page listing every file the statements are written in.
     */
    public const FILES = 'files.html';

    /**
     * The page listing every finding.
     */
    public const FINDINGS = 'findings.html';

    private Catalog $catalog;

    private CatalogIndex $index;

    private CatalogStatistics $statistics;

    private HtmlText $text;

    /**
     * @var array<string, string>
     */
    private array $tablePages;

    /**
     * @var array<string, string>
     */
    private array $classPages;

    /**
     * @var array<string, string>
     */
    private array $filePages;

    /**
     * Lays a catalog out as pages.
     */
    public function __construct(Catalog $catalog, ?HtmlText $text = null, public readonly SqlFormatter $formatter = new SqlFormatter())
    {
        $this->text = $text ?? new HtmlText();
        $this->catalog = $catalog->sorted();
        $this->index = new CatalogIndex($this->catalog);
        $this->statistics = new CatalogStatistics($this->catalog);
        $this->tablePages = $this->pagesFor(array_keys($this->index->byTable()), 'tables/');
        $this->classPages = $this->pagesFor(array_keys($this->index->byClass()), 'classes/');
        $this->filePages = $this->pagesFor(array_keys($this->index->byFile()), 'files/');
    }

    /**
     * The catalog, in reporting order.
     */
    public function catalog(): Catalog
    {
        return $this->catalog;
    }

    /**
     * The catalog grouped along every route.
     */
    public function index(): CatalogIndex
    {
        return $this->index;
    }

    /**
     * The counts the pages are read through.
     */
    public function statistics(): CatalogStatistics
    {
        return $this->statistics;
    }

    /**
     * One page name per key, unique even when two keys slug alike.
     *
     * @param list<string> $keys
     * @return array<string, string>
     */
    public function pagesFor(array $keys, string $directory): array
    {
        $pages = [];
        $taken = [];
        foreach ($keys as $key) {
            $slug = $this->text->slug($key);
            $slug = $slug === '' ? 'unnamed' : $slug;
            $candidate = $slug;
            for ($n = 2; isset($taken[$candidate]); $n++) {
                $candidate = $slug . '-' . $n;
            }
            $taken[$candidate] = true;
            $pages[$key] = $directory . $candidate . '.html';
        }

        return $pages;
    }

    /**
     * The page one statement is written on.
     */
    public function statementPage(string $id): string
    {
        return 'statements/' . $id . '.html';
    }

    /**
     * The page one table is written on, or the tables listing for a table the catalog does not name.
     */
    public function tablePage(string $table): string
    {
        return $this->tablePages[$table] ?? self::TABLES;
    }

    /**
     * The page one class is written on, or the namespaces listing for a class the catalog does not know.
     */
    public function classPage(string $class): string
    {
        return $this->classPages[$class] ?? self::NAMESPACES;
    }

    /**
     * The page one file is written on, or the files listing for a file the catalog does not hold.
     */
    public function filePage(string $file): string
    {
        return $this->filePages[$file] ?? self::FILES;
    }

    /**
     * The identifier a function's statements are grouped under on a class or file page.
     */
    public function functionAnchor(string $function): string
    {
        return 'fn-' . $this->text->slug(Scope::of($function)->function());
    }

    /**
     * The address of the section listing what one function issues.
     *
     * A method is listed on its class's page; a function, and top-level code,
     * on the page of the file it is written in.
     */
    public function functionUrl(CatalogEntry $entry): string
    {
        $scope = Scope::of($entry->site->function);
        $page = $scope->class === null ? $this->filePage($entry->site->file) : $this->classPage($scope->class);

        return $page . '#' . $this->functionAnchor($entry->site->function);
    }

    /**
     * Every table that has a page, most named first.
     *
     * @return list<string>
     */
    public function tables(): array
    {
        return array_keys($this->tablePages);
    }

    /**
     * Every class that has a page, in name order.
     *
     * @return list<string>
     */
    public function classes(): array
    {
        return array_keys($this->classPages);
    }

    /**
     * Every file that has a page, in path order.
     *
     * @return list<string>
     */
    public function files(): array
    {
        return array_keys($this->filePages);
    }

    /**
     * What a link from that page has to be written through to reach the root.
     */
    public function prefixOf(string $page): string
    {
        return str_repeat('../', substr_count($page, '/'));
    }
}
