<?php

declare(strict_types=1);

namespace SqlCatalog;

use SqlCatalog\Analysis\EntryFactory;
use SqlCatalog\Analysis\Interpreter;
use SqlCatalog\Catalog\AnalysisProblem;
use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Extension\ExtensionRegistry;
use SqlCatalog\Php\DeclaredGlobals;
use SqlCatalog\Php\ParsedFile;
use SqlCatalog\Php\ProgramIndexBuilder;
use SqlCatalog\Php\SourceParser;
use SqlCatalog\Php\SyntaxException;
use SqlCatalog\Source\SourceFile;
use SqlCatalog\Source\SourceScanner;

/**
 * Reads PHP source and reports the SQL statements it can issue.
 *
 * The whole source tree is indexed before any of it is walked, so a statement
 * assembled from a constant, an enum or a method in another file still resolves.
 *
 * @visibility public
 * @example Cataloguing a query written inline
 *     $catalog = (new \SqlCatalog\Analyzer())->analyzeSource([
 *         'users.php' => '<?php function all(PDO $db) { return $db->query("SELECT id FROM users"); }',
 *     ]);
 *     $catalog->entries()[0]->sql() // => 'SELECT id FROM users'
 *     $catalog->entries()[0]->tables // => ['users']
 *
 * @example Reporting a value spliced into the statement text
 *     $catalog = (new \SqlCatalog\Analyzer())->analyzeSource([
 *         'unsafe.php' => '<?php function find(PDO $db) { return $db->query("SELECT * FROM users WHERE id = " . $_GET["id"]); }',
 *     ]);
 *     $catalog->entries()[0]->sql() // => 'SELECT * FROM users WHERE id = {$}'
 *     $catalog->entries()[0]->severity()->value // => 'high'
 */
final class Analyzer
{
    private ExtensionRegistry $extensions;

    private SourceParser $parser;

    private ProgramIndexBuilder $indexes;

    private EntryFactory $entries;

    /**
     * @param ExtensionRegistry|null $extensions The extensions available to the run, or null for the built-in ones
     */
    public function __construct(?ExtensionRegistry $extensions = null)
    {
        $this->extensions = $extensions ?? ExtensionRegistry::withBuiltins();
        $this->parser = new SourceParser();
        $this->indexes = new ProgramIndexBuilder();
        $this->entries = new EntryFactory();
    }

    /**
     * The extensions this analyzer can be asked for.
     */
    public function extensions(): ExtensionRegistry
    {
        return $this->extensions;
    }

    /**
     * The statements the files under the given paths can issue.
     *
     * @param list<string> $paths Files and directories to read
     * @param string $root The directory reported paths are relative to
     * @param list<string> $excluded Patterns, matched against reported paths, to leave out
     * @throws Source\SourceScanException When one of the paths cannot be read
     * @throws Extension\UnknownExtensionException When the options name an extension that is not registered
     */
    public function analyzePaths(
        array $paths,
        ?AnalysisOptions $options = null,
        string $root = '.',
        array $excluded = [],
    ): Catalog {
        $sources = [];
        foreach ((new SourceScanner($root, $excluded))->scan($paths) as $file) {
            $sources[$file->path] = $file->code;
        }

        return $this->analyzeSource($sources, $options);
    }

    /**
     * The statements the given sources can issue.
     *
     * @param array<string, string> $sources Source text, keyed by the path the catalog reports
     * @throws Extension\UnknownExtensionException When the options name an extension that is not registered
     */
    public function analyzeSource(array $sources, ?AnalysisOptions $options = null): Catalog
    {
        $options ??= new AnalysisOptions();
        $files = [];
        $problems = [];
        foreach ($sources as $path => $code) {
            $parsed = $this->parse(new SourceFile($path, $code));
            if ($parsed instanceof ParsedFile) {
                $files[] = $parsed;
                continue;
            }
            $problems[] = $parsed;
        }

        return new Catalog($this->entriesOf($files, $options), $problems);
    }

    /**
     * The file parsed, or the problem that stopped it from being parsed.
     */
    public function parse(SourceFile $file): ParsedFile|AnalysisProblem
    {
        try {
            return $this->parser->parse($file->path, $file->code);
        } catch (SyntaxException $exception) {
            return new AnalysisProblem($file->path, $exception->getMessage());
        }
    }

    /**
     * The catalog entries of a parsed source tree.
     *
     * @param list<ParsedFile> $files
     * @return list<CatalogEntry>
     * @throws Extension\UnknownExtensionException When the options name an extension that is not registered
     */
    public function entriesOf(array $files, AnalysisOptions $options): array
    {
        $sinks = $this->extensions->sinksOf($options->extensions);
        $interpreter = new Interpreter(
            $this->indexes->build($files),
            $sinks,
            $options->budget(),
            new DeclaredGlobals($this->extensions->globalsOf($options->extensions)),
            $options->dialect,
            $this->extensions->modelProvidersOf($options->extensions),
        );

        return $this->sortRecords($this->entries->build($interpreter->analyze($files)));
    }

    /**
     * The entries in the order a report lists them.
     *
     * @param list<CatalogEntry> $entries
     * @return list<CatalogEntry>
     */
    public function sortRecords(array $entries): array
    {
        return (new Catalog($entries))->sorted()->entries();
    }
}
