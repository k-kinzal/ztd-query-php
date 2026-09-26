<?php

declare(strict_types=1);

namespace Requirements\Test;

use Override;
use RuntimeException;

/**
 * The built-in runner for PHPUnit test methods selected as Class::method.
 *
 * The filter selects exactly that method and each of its data sets; risky, warning, skipped
 * and incomplete tests fail the run.
 */
final class PhpUnitRunner implements RunnerExtension
{
    /**
     * Runs one PHPUnit test method.
     *
     * @param RunnerConfig $config The PHPUnit command, working directory and timeout
     * @param string $target A fully qualified Class::method reference
     *
     * @return TestResult The verdict, or an error for a malformed target
     *
     * @throws RuntimeException When the temporary report directory cannot be created
     */
    #[Override]
    public function run(RunnerConfig $config, string $target): TestResult
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]*::[A-Za-z_][A-Za-z0-9_]*$/D', $target) !== 1) {
            return new TestResult('error', 0, 'PHPUnit targets must be fully qualified Class::method references.');
        }
        $filter = '~^' . preg_quote($target, '~') . '(?: with data set .+)?$~';
        return (new ProcessRunner())->run($config, static fn (string $directory): array => ['--filter', $filter, '--log-junit', $directory . '/phpunit.xml', '--fail-on-risky', '--fail-on-warning', '--fail-on-skipped', '--fail-on-incomplete', '--do-not-cache-result']);
    }
}
