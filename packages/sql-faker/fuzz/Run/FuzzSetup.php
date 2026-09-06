<?php

declare(strict_types=1);

namespace SqlFaker\Fuzz\Run;

use Faker\Factory;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use SqlFaker\Coverage\GrammarCoverage;
use SqlFaker\Fuzz\Input\FuzzPlanDecoder;
use SqlFaker\Grammar\Derivation\CompletionCosts;
use SqlFaker\Grammar\LexicalGrammar;
use SqlFaker\MySqlProvider;
use SqlFaker\PostgreSqlProvider;
use SqlFaker\SqliteProvider;

/**
 * Fixes dialect and grammar once, without headers or SQL-kind branches in target inputs.
 */
final class FuzzSetup
{
    /**
     * Fixed run collaborator or directory.
     */
    public readonly MySqlProvider|PostgreSqlProvider|SqliteProvider $provider;
    /**
     * Fixed run collaborator or directory.
     */
    public readonly GrammarCoverage $coverage;
    /**
     * Fixed run collaborator or directory.
     */
    public readonly LexicalGrammar $lexical;
    /**
     * Fixed run collaborator or directory.
     */
    public readonly CompletionCosts $costs;
    /**
     * Fixed run collaborator or directory.
     */
    public readonly FuzzPlanDecoder $decoder;
    /**
     * Fixed run collaborator or directory.
     */
    public readonly string $corpus;
    /**
     * Fixed run collaborator or directory.
     */
    public readonly string $reports;

    /**
     * Sets up independent corpus, coverage and run metadata directories.
     *
     * @throws InvalidArgumentException When dialect or storage settings are invalid
     */
    public function __construct(public readonly string $database, public readonly string $version, bool $persistent = true)
    {
        $root = dirname(__DIR__);
        $this->corpus = self::directory(self::corpusArgument($root . '/corpus/' . $database));
        $storage = self::directory(self::environment('FUZZ_COVERAGE_DIRECTORY', $root . '/coverage/' . $database));
        $this->reports = self::directory(self::environment('FUZZ_REPORT_DIRECTORY', dirname($root) . '/build/fuzz/' . $database));
        self::separate($this->corpus, $storage);
        self::separate($this->corpus, $this->reports);
        $this->coverage = new GrammarCoverage(storageDirectory: $persistent ? $storage : null);
        $faker = Factory::create();
        [$this->provider, $this->lexical] = match ($database) {
            'mysql' => [new MySqlProvider($faker, $version, $this->coverage), new \SqlFaker\MySql\LexicalGrammar($faker, $version)],
            'pg' => [new PostgreSqlProvider($faker, $version, $this->coverage), new \SqlFaker\PostgreSql\LexicalGrammar($faker, $version)],
            'sqlite' => [new SqliteProvider($faker, $version, $this->coverage), new \SqlFaker\Sqlite\LexicalGrammar($faker, $version)],
            default => throw new InvalidArgumentException('Unknown fixed fuzz database: ' . $database),
        };
        $inventory = $this->coverage->inventory();
        $this->costs = new CompletionCosts($inventory->grammar, $this->lexical->supports(...));
        $this->decoder = new FuzzPlanDecoder($this->costs->rule($inventory->root, true), self::positive('FUZZ_MAX_EXPANSIONS', 5000));
    }

    /**
     * Validates the actual corpus argument, including custom CLI corpus paths.
     */
    public static function corpusArgument(string $default): string
    {
        /**
         * @var list<string> $arguments
         */
        $arguments = $_SERVER['argv'] ?? [];
        $index = array_search('fuzz', $arguments, true);
        if ($index !== false && isset($arguments[$index + 2]) && !str_starts_with($arguments[$index + 2], '--')) {
            return $arguments[$index + 2];
        }
        return self::environment('FUZZ_CORPUS_DIRECTORY', $default);
    }

    /**
     * Resolves existing symlinks before checking directory ancestry.
     *
     * @throws InvalidArgumentException When a directory cannot be used
     */
    public static function directory(string $path): string
    {
        if (!is_dir($path) && !@mkdir($path, 0777, true) && !is_dir($path)) {
            throw new InvalidArgumentException('Cannot create fuzz directory: ' . $path);
        }
        $real = realpath($path);
        if ($real === false) {
            throw new InvalidArgumentException('Cannot resolve fuzz directory: ' . $path);
        }
        return $real;
    }

    /**
     * Rejects metadata under the directory recursively consumed as raw fuzz inputs.
     *
     * @throws InvalidArgumentException When paths overlap
     */
    public static function separate(string $corpus, string $metadata): void
    {
        if ($corpus === $metadata || str_starts_with($metadata, $corpus . DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('Coverage and reports must be outside the corpus directory.');
        }
    }

    /**
     * Reads a configured positive integer without accepting silent casts.
     *
     * @throws InvalidArgumentException When a numeric setting is malformed
     */
    public static function positive(string $name, int $default): int
    {
        $value = self::environment($name, (string) $default);
        if (preg_match('/^[1-9][0-9]{0,6}$/D', $value) !== 1) {
            throw new InvalidArgumentException($name . ' must be a positive integer.');
        }
        return (int) $value;
    }

    /**
     * Reads one optional run setting.
     */
    public static function environment(string $name, string $default): string
    {
        $value = getenv($name);
        return $value === false ? $default : $value;
    }

    /**
     * Creates run diagnostics carrying the actual database and PHP versions.
     */
    public function checkpoint(string $databaseVersion): FuzzCheckpoint
    {
        return new FuzzCheckpoint(
            $this->coverage,
            $this->reports,
            ['database' => $this->database,
            'databaseVersion' => $databaseVersion, 'grammarVersion' => $this->version, 'phpVersion' => PHP_VERSION,
            'decoderFormat' => FuzzPlanDecoder::FORMAT, 'maximumExpansions' => $this->decoder->maximum,
            'minimumExpansions' => $this->decoder->minimum, 'harnessRevision' => hash('sha256', (string) file_get_contents(__FILE__))],
            self::positive('FUZZ_CHECKPOINT_GENERATIONS', 100),
            self::positive('FUZZ_CHECKPOINT_SECONDS', 30),
            $this->initialInputCount()
        );
    }

    /**
     * Counts startup files for honest replay progress without interpreting their bytes.
     */
    public function initialInputCount(): int
    {
        $count = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->corpus)) as $file) {
            if ($file instanceof SplFileInfo && $file->isFile()) {
                ++$count;
            }
        }
        return $count;
    }
}
