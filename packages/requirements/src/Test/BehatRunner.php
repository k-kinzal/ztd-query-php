<?php

declare(strict_types=1);

namespace Requirements\Test;

final class BehatRunner implements RunnerExtension
{
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
