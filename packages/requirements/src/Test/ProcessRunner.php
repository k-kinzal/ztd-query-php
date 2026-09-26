<?php

declare(strict_types=1);

namespace Requirements\Test;

use RuntimeException;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Process;

/**
 * Runs a runner command that writes JUnit XML and reads the verdict from its reports.
 *
 * The arguments are appended to the configured command without a shell, the reports are
 * written to a fresh temporary directory and removed afterwards. A process that cannot start
 * or exceeds its timeout becomes an "error" result.
 *
 * @visibility public
 *
 * @example Running a command that reports one passing test case
 *     $config = new \Requirements\Test\RunnerConfig('custom', [PHP_BINARY, '-r', 'file_put_contents($argv[1], "<testsuite><testcase name=\"a\"/></testsuite>");'], sys_get_temp_dir());
 *     $result = (new \Requirements\Test\ProcessRunner())->run($config, static fn (string $directory): array => [$directory . '/report.xml']);
 *     [$result->status, $result->tests] // => ['passed', 1]
 */
final class ProcessRunner
{
    /**
     * Runs the command and reads the JUnit reports it wrote.
     *
     * @param RunnerConfig $config The command, working directory and timeout
     * @param callable(string): list<string> $arguments Builds the arguments from the report directory
     *
     * @return TestResult The verdict read from the reports
     *
     * @throws RuntimeException When the temporary report directory cannot be created
     */
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
        } catch (ExceptionInterface $error) {
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
