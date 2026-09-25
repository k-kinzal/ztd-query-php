<?php

declare(strict_types=1);

namespace SqlCatalog\Cli;

use RuntimeException;
use SqlCatalog\Core\Catalog\Catalog;
use SqlCatalog\Core\Reporter\ReporterRegistry;
use SqlCatalog\Facade\AnalysisOptions;
use SqlCatalog\Facade\Analyzer;
use SqlCatalog\Facade\Configuration;
use SqlCatalog\Facade\InvalidConfigurationException;
use Throwable;

/**
 * The `sql-catalog` command.
 *
 * @visibility public
 * @example Cataloguing a directory and reading the result from standard output
 *     $command = new \SqlCatalog\Cli\CatalogCommand();
 *     $result = $command->run(['--help']);
 *     $result->exitCode->value // => 0
 *     str_contains($result->output, 'sql-catalog') // => true
 */
final class CatalogCommand
{
    private Analyzer $analyzer;

    private ReporterRegistry $reporters;

    private CommandLineParser $parser;

    private ArtifactWriter $writer;

    /**
     * @param Analyzer|null $analyzer The analyzer to run, or null for one with the built-in extensions
     * @param ReporterRegistry|null $reporters The reporters to render with, or null for the built-in ones
     */
    public function __construct(?Analyzer $analyzer = null, ?ReporterRegistry $reporters = null)
    {
        $this->analyzer = $analyzer ?? new Analyzer();
        $this->reporters = $reporters ?? \SqlCatalog\Facade\ReporterRegistry::withBuiltins();
        $this->parser = new CommandLineParser();
        $this->writer = new ArtifactWriter();
    }

    /**
     * Runs the command with the given arguments.
     *
     * @param list<string> $arguments The arguments, without the command name
     */
    public function run(array $arguments): CommandResult
    {
        try {
            return $this->execute($this->parser->parse($arguments));
        } catch (InvalidCommandLineException|InvalidConfigurationException $exception) {
            return CommandResult::failure(ExitCode::InvalidCommandLine, $exception->getMessage());
        } catch (RuntimeException $exception) {
            return CommandResult::failure(ExitCode::SourceUnreadable, $exception->getMessage());
        }
    }

    /**
     * Runs the command for an already parsed command line.
     *
     * @throws InvalidConfigurationException When the requested configuration cannot be loaded
     * @throws InvalidCommandLineException When no path was given to analyze
     * @throws \SqlCatalog\Core\Source\SourceScanException When a path cannot be read
     * @throws \SqlCatalog\Core\Extension\UnknownExtensionException When an extension is not registered
     * @throws \SqlCatalog\Core\Reporter\UnknownReporterException When the reporter is not registered
     * @throws WriteFailureException When the report cannot be written
     */
    public function execute(CommandLine $command): CommandResult
    {
        $query = $this->answerQuery($command);
        if ($query !== null) {
            return $query;
        }
        if ($command->paths === []) {
            throw new InvalidCommandLineException('Name at least one file or directory to analyze.');
        }

        $analyzer = $this->configuredAnalyzer($command->configuration);
        $catalog = $command->filter->apply($analyzer->analyzePaths(
            $command->paths,
            new AnalysisOptions($command->extensions, dialect: $command->dialect),
            $command->root,
            $command->excluded,
        ));

        return $this->report($command, $catalog);
    }

    /**
     * Loads the requested configuration, reporting failures as configuration errors.
     *
     * @throws InvalidConfigurationException When loading or applying the configuration fails
     */
    public function configuredAnalyzer(Configuration $configuration): Analyzer
    {
        try {
            return $this->analyzer->withConfiguration($configuration);
        } catch (Throwable $exception) {
            throw new InvalidConfigurationException(sprintf('Cannot load configuration "%s": %s', $configuration->file ?? '(provided settings)', $exception->getMessage()), 0, $exception);
        }
    }

    /**
     * The answer to a command line that asks for information, or null when it does not.
     */
    public function answerQuery(CommandLine $command): ?CommandResult
    {
        $usage = new UsageText($this->analyzer->extensions(), $this->reporters);
        if ($command->help) {
            return CommandResult::ok($usage->help());
        }
        if ($command->listExtensions) {
            return CommandResult::ok($usage->extensions());
        }

        return $command->listReporters ? CommandResult::ok($usage->reporters()) : null;
    }

    /**
     * Renders the catalog where the command line asked for it.
     *
     * @throws \SqlCatalog\Core\Reporter\UnknownReporterException When the reporter is not registered
     * @throws WriteFailureException When the report cannot be written
     */
    public function report(CommandLine $command, Catalog $catalog): CommandResult
    {
        $artifacts = $this->reporters->get($command->reporter)->render($catalog);
        $status = $this->status($command, $catalog);

        if ($command->output === null) {
            return new CommandResult($status, $artifacts->primary() ?? implode("\n", $artifacts->all()));
        }

        $written = $this->writer->write($command->output, $artifacts);

        return new CommandResult($status, implode("\n", $written) . "\n");
    }

    /**
     * What to tell the shell about the statements that were found.
     */
    public function status(CommandLine $command, Catalog $catalog): ExitCode
    {
        if ($command->failOn === null) {
            return ExitCode::Success;
        }
        foreach ($catalog as $entry) {
            if ($entry->severity()->atLeast($command->failOn)) {
                return ExitCode::FindingsReported;
            }
        }

        return ExitCode::Success;
    }
}
