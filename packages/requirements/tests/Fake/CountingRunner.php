<?php

declare(strict_types=1);

namespace Tests\Fake;

use Override;
use Requirements\Test\RunnerConfig;
use Requirements\Test\RunnerExtension;
use Requirements\Test\TestResult;

/**
 * A runner that records every target it runs in executions.txt of its working directory.
 *
 * Every target passes with one test, except "empty", which passes with none.
 */
final class CountingRunner implements RunnerExtension
{
    /**
     * Records and passes a target.
     *
     * @param RunnerConfig $config The runner configuration
     * @param string $target The target
     *
     * @return TestResult A pass with one test, or with none for "empty"
     */
    #[Override]
    public function run(RunnerConfig $config, string $target): TestResult
    {
        file_put_contents($config->directory . '/executions.txt', $target . "\n", FILE_APPEND);
        return new TestResult('passed', $target === 'empty' ? 0 : 1);
    }
}
