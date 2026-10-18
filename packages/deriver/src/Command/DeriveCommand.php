<?php

declare(strict_types=1);

namespace Deriver\Command;

use Deriver\Configuration\ReportFormat;
use Deriver\Deriver;
use Deriver\Deriver\DeriverConfig;
use Deriver\Exception\CollectorException;
use Deriver\Exception\ConfigurationException;
use Deriver\Exception\InvalidInputException;
use Deriver\Exception\ParserException;
use Deriver\Exception\ReportingException;
use Deriver\Exception\ResolutionException;
use Deriver\Finder;
use Deriver\Reporter\JsonReporter;
use Deriver\Reporter\TextReporter;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Collects selected paths, derives one selector, and writes a report to standard output.
 * @visibility namespace
 */
final class DeriveCommand extends Command
{
    /**
     * Registers the single command exposed by the deliver executable.
     */
    public function __construct()
    {
        parent::__construct('deliver');
    }

    /**
     * Defines source selection, configuration, and report options.
     */
    #[Override]
    protected function configure(): void
    {
        $this->setDescription('Derive candidate values from PHP source without executing it.')
            ->addArgument('query', InputArgument::REQUIRED, 'Selector, optionally prefixed by callers: entry -> ... -> App\\answer#return.')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Load a YAML configuration file.')
            ->addOption('path', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Search root; repeat for multiple directories. Defaults to the current directory.')
            ->addOption('include', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Root-relative include wildcard; repeat to combine patterns. Defaults to **/*.php.')
            ->addOption('exclude', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Root-relative exclude wildcard; exclusions win.')
            ->addOption('php-version', null, InputOption::VALUE_REQUIRED, 'Target PHP major.minor version; defaults to the library setting.')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Report format: text or json. Defaults to text.');
    }

    /**
     * Uses exit code 0 for completed exploration, 1 for interruption or execution failure, and 2 for invalid input.
     */
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $query = $input->getArgument('query');
            if (!is_string($query) || $query === '') {
                throw new InvalidInputException('A nonempty selector is required.');
            }
            $configuration = (new Input())->configuration($input);
            $finder = new Finder();
            foreach ($configuration->paths as $path) {
                $finder->in($path);
            }
            foreach ($configuration->include as $pattern) {
                $finder->include($pattern);
            }
            foreach ($configuration->exclude as $pattern) {
                $finder->exclude($pattern);
            }
            $files = $finder->files();
            if ($files === []) {
                throw new InvalidInputException('No source files matched the selected paths and patterns.');
            }
            $settings = new DeriverConfig($configuration->phpVersion ?? DeriverConfig::default()->phpVersion, $configuration->integerBits, $configuration->floatPrecision, $configuration->environment, $configuration->standardEvaluators);
            $candidates = Deriver::prepare($files, $settings)->derive($query);
            $reporter = match ($configuration->format) {
                ReportFormat::Text => new TextReporter(),
                ReportFormat::Json => new JsonReporter(),
            };
            $output->write($reporter->report($candidates), false, OutputInterface::OUTPUT_RAW);
            return $candidates->interrupted ? self::FAILURE : self::SUCCESS;
        } catch (ConfigurationException | InvalidInputException $error) {
            $status = self::INVALID;
        } catch (CollectorException | ParserException | ResolutionException | ReportingException $error) {
            $status = self::FAILURE;
        }
        $errors = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $errors->writeln('Error: ' . $error->getMessage(), OutputInterface::OUTPUT_RAW);
        return $status;
    }
}
