<?php

declare(strict_types=1);

namespace SqlCatalog\Cli;

use SqlCatalog\Catalog\Severity;
use SqlCatalog\Configuration;
use SqlCatalog\Filter\CatalogFilter;

/**
 * What the command was asked to do.
 *
 * @visibility root
 */
final class CommandLine
{
    /**
     * @param list<string> $paths The files and directories to analyze
     * @param string|null $output The directory to write the report into, or null for standard output
     * @param string $reporter The reporter to render with
     * @param list<string> $extensions The extensions whose database calls are recognised
     * @param CatalogFilter $filter Which of the statements found to keep
     * @param list<string> $excluded Patterns of source files to skip
     * @param string $root The directory reported paths are relative to
     * @param Severity|null $failOn The severity that makes the run report a failure
     * @param bool $help Whether the command was asked for its help
     * @param bool $listExtensions Whether the command was asked to list its extensions
     * @param string|null $config The catalog YAML file selected for this run
     * @param bool $listReporters Whether the command was asked to list its reporters
     */
    public function __construct(
        public readonly array $paths = [],
        public readonly ?string $output = null,
        public readonly string $reporter = 'text',
        public readonly array $extensions = ['pdo', 'mysqli'],
        public readonly CatalogFilter $filter = new CatalogFilter(),
        public readonly array $excluded = [],
        public readonly string $root = '.',
        public readonly ?Severity $failOn = null,
        public readonly bool $help = false,
        public readonly bool $listExtensions = false,
        public readonly bool $listReporters = false,
        public readonly ?string $config = null,
        public readonly Configuration $configuration = new Configuration(),
    ) {
    }

    /**
     * Whether the command was asked for information rather than for an analysis.
     */
    public function isQuery(): bool
    {
        return $this->help || $this->listExtensions || $this->listReporters;
    }
}
