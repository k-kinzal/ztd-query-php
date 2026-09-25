<?php

declare(strict_types=1);

namespace Tests\Fake;

use Symfony\Component\Process\Process;

/**
 * Runs the package's bin/requirements in a working directory, as a user would.
 *
 * SHELL_VERBOSITY is removed from the child's environment: an in-process console run with
 * --quiet leaves it set to -1, which would silence every later command.
 */
final class CommandLine
{
    /**
     * Runs the command and waits for it.
     *
     * @param list<string> $arguments The arguments after the program name
     * @param string $directory The working directory
     *
     * @return Process The finished process, with its exit code and output
     */
    public static function run(array $arguments, string $directory): Process
    {
        $process = new Process([PHP_BINARY, dirname(__DIR__, 2) . '/bin/requirements', ...$arguments], $directory, ['SHELL_VERBOSITY' => false]);
        $process->run();
        return $process;
    }
}
