<?php

declare(strict_types=1);

namespace Requirements\Tests\Fixtures\Examples\Extensions;

use Requirements\Test\ProcessRunner;
use Requirements\Test\RunnerConfig;
use Requirements\Test\RunnerExtension;
use Requirements\Test\TestResult;

final class ScenarioRunner implements RunnerExtension
{
    public function run(RunnerConfig $config, string $target): TestResult
    {
        return (new ProcessRunner())->run($config, static fn (string $directory): array => ['--case', $target, '--junit', $directory . '/results.xml']);
    }
}
