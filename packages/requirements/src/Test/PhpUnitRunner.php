<?php

declare(strict_types=1);

namespace Requirements\Test;

final class PhpUnitRunner implements RunnerExtension
{
    public function run(RunnerConfig $config, string $target): TestResult
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]*::[A-Za-z_][A-Za-z0-9_]*$/D', $target) !== 1) {
            return new TestResult('error', 0, 'PHPUnit targets must be fully qualified Class::method references.');
        }
        $filter = '~^' . preg_quote($target, '~') . '(?: with data set .+)?$~';
        return (new ProcessRunner())->run($config, static fn (string $directory): array => ['--filter', $filter, '--log-junit', $directory . '/phpunit.xml', '--fail-on-risky', '--fail-on-warning', '--fail-on-skipped', '--fail-on-incomplete', '--do-not-cache-result']);
    }
}
