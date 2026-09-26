<?php

declare(strict_types=1);

namespace Requirements\Console;

use JsonException;
use Requirements\Config\Loader;
use Requirements\Input\InvalidInputException;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Runs one command: loads the project, executes the command and reports the result.
 */
final class CommandHandler
{
    /**
     * @param string $command The command name
     */
    public function __construct(private readonly string $command)
    {
    }

    /**
     * Runs the command.
     *
     * @param InputInterface $input The parsed command line
     * @param OutputInterface $output Receives the JSON report or the terminal tables
     *
     * @return int 0 when the report passed, 1 otherwise
     *
     * @throws InvalidInputException When the configuration, a definition or an option is invalid
     * @throws JsonException When a document or the report cannot be encoded
     * @throws RuntimeException When a file cannot be written or read
     */
    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $options = Options::fromInput($this->command, $input);
        $project = (new Loader())->load($options->text('config', 'requirements.yaml') ?? 'requirements.yaml');
        $report = (new Executor())->execute($project, $options);
        if ($options->flag('json')) {
            $output->writeln(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), OutputInterface::OUTPUT_RAW);
        } else {
            (new Reporter())->render($report, $options, $input, $output);
        }
        return ($report['passed'] ?? true) === true ? 0 : 1;
    }
}
