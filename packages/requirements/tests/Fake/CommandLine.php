<?php

declare(strict_types=1);

namespace Tests\Fake;

use Symfony\Component\Process\Process;

/**
 * Runs the package's bin/requirements in a working directory, as a user would.
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
        $process = new Process([PHP_BINARY, dirname(__DIR__, 2) . '/bin/requirements', ...$arguments], $directory);
        $process->run();
        return $process;
    }
}
