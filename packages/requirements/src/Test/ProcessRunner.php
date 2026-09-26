<?php

declare(strict_types=1);

namespace Requirements\Test;

use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class ProcessRunner
{
    /** @param callable(string): list<string> $arguments */
    public function run(RunnerConfig $config, callable $arguments): TestResult
    {
        $directory = sys_get_temp_dir() . '/requirements-' . bin2hex(random_bytes(12));
        if (!mkdir($directory, 0700)) {
            throw new RuntimeException('Cannot create temporary report directory.');
        }
        try {
            $process = new Process([...$config->command, ...$arguments($directory)], $config->directory, null, null, $config->timeout);
            $exit = $process->run();
            $files = glob($directory . '/*.xml');
            return (new JUnit())->read($files === false ? [] : $files, $exit, $process->getOutput() . $process->getErrorOutput());
        } catch (Throwable $error) {
            return new TestResult('error', 0, $error->getMessage());
        } finally {
            $files = glob($directory . '/*');
            foreach ($files === false ? [] : $files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($directory);
        }
    }
}
