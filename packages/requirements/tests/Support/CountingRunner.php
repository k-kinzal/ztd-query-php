<?php

declare(strict_types=1);

namespace Requirements\Tests\Support;

use Requirements\Test\RunnerConfig;
use Requirements\Test\RunnerExtension;
use Requirements\Test\TestResult;

final class CountingRunner implements RunnerExtension
{
    public function run(RunnerConfig $config, string $target): TestResult
    {
        file_put_contents($config->directory . '/executions.txt', $target . "\n", FILE_APPEND);
        return new TestResult('passed', 1);
    }
}
