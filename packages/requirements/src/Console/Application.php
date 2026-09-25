<?php

declare(strict_types=1);

namespace Requirements\Console;

use JsonException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * The requirements command line: lint, check, coverage, spec and format.
 *
 * Exit codes are 0 on success, 1 for a failed check, gate, verification or format check, and
 * 2 for invalid configuration, options or any other error. With --json every outcome,
 * errors included, is a JSON document on standard output.
 *
 * @visibility public
 *
 * @example Showing the overview when no command is given
 *     (new \Requirements\Console\Application())->run(['--quiet']) // => 0
 * @example Rejecting an unknown command
 *     (new \Requirements\Console\Application())->run(['unknown', '--quiet']) // => 2
 */
final class Application
{
    /**
     * Runs one command.
     *
     * @param list<string> $arguments The command-line arguments after the program name
     *
     * @return int The exit code
     *
     * @throws JsonException When a JSON error message cannot be encoded
     */
    public function run(array $arguments): int
    {
        $input = new ArgvInput(['requirements', ...$arguments]);
        $output = new ConsoleOutput();
        try {
            $console = (new CommandLine())->create();
            $overview = new Overview();
            if ($overview->requested($console, $input)) {
                $overview->show($console, $output, $arguments);
                return 0;
            }
            return $console->run($input, $output);
        } catch (Throwable $error) {
            if (in_array('--json', $arguments, true)) {
                $output->writeln(json_encode(['passed' => false, 'errors' => [$error->getMessage()]], JSON_THROW_ON_ERROR), OutputInterface::OUTPUT_RAW);
            } else {
                (new SymfonyStyle($input, $output->getErrorOutput()))->error(OutputFormatter::escape($error->getMessage()));
            }
            return 2;
        }
    }
}
