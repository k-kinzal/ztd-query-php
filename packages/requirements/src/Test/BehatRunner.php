<?php

declare(strict_types=1);

namespace Requirements\Test;

use Override;
use RuntimeException;

/**
 * The built-in runner for Behat scenarios selected as file.feature:line.
 *
 * The line must hold an English Scenario or Scenario Outline header; an outline runs all of
 * its examples. Undefined and pending steps fail the run.
 */
final class BehatRunner implements RunnerExtension
{
    /**
     * Runs one Behat scenario.
     *
     * @param RunnerConfig $config The Behat command, working directory and timeout
     * @param string $target The feature file and the line of the scenario header
     *
     * @return TestResult The verdict, or an error for a malformed or missing target
     *
     * @throws RuntimeException When the temporary report directory cannot be created
     */
    #[Override]
    public function run(RunnerConfig $config, string $target): TestResult
    {
        if (preg_match('/^(.+\.feature):([1-9][0-9]*)$/D', $target, $match) !== 1 || str_starts_with($target, '-')) {
            return new TestResult('error', 0, 'Behat targets must select one scenario by file.feature:line.');
        }
        $file = str_starts_with($match[1], '/') ? $match[1] : $config->directory . '/' . $match[1];
        $lines = is_file($file) ? file($file) : false;
        $line = $lines === false ? '' : ($lines[(int) $match[2] - 1] ?? '');
        if (preg_match('/^\s*Scenario(?: Outline)?:\s*\S/', $line) !== 1) {
            return new TestResult('error', 0, 'The target line must be an English Scenario or Scenario Outline header.');
        }
        return (new ProcessRunner())->run($config, static fn (string $directory): array => ['--strict', '--no-interaction', '--no-snippets', '--format', 'junit', '--out', $directory, '--', $target]);
    }
}
