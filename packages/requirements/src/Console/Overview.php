<?php

declare(strict_types=1);

namespace Requirements\Console;

use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Shows the application overview when no command, or only help, is requested.
 *
 * The overview needs no project, so it works without a configuration file.
 */
final class Overview
{
    /**
     * Tells whether the command line asks for the overview.
     *
     * @param ConsoleApplication $console The application with its global options
     * @param ArgvInput $input The command line; it is not modified
     *
     * @return bool True without a command or with the help command, unless --version is given
     */
    public function requested(ConsoleApplication $console, ArgvInput $input): bool
    {
        try {
            $helpInput = clone $input;
            $helpInput->bind($console->getDefinition());
            return in_array($helpInput->getFirstArgument(), [null, 'help'], true)
                && !$helpInput->hasParameterOption(['--version', '-V']);
        } catch (ExceptionInterface) {
            return false;
        }
    }

    /**
     * Writes the overview, honoring --ansi, --no-ansi and --quiet.
     *
     * @param ConsoleApplication $console The application to describe
     * @param OutputInterface $output Receives the overview
     * @param list<string> $arguments The command-line arguments
     */
    public function show(ConsoleApplication $console, OutputInterface $output, array $arguments): void
    {
        if (in_array('--no-ansi', $arguments, true)) {
            $output->setDecorated(false);
        }
        if (in_array('--ansi', $arguments, true)) {
            $output->setDecorated(true);
        }
        if (in_array('--quiet', $arguments, true) || in_array('-q', $arguments, true)) {
            $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
        }
        (new DescriptorHelper())->describe($output, $console);
    }
}
