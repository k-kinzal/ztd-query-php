<?php

declare(strict_types=1);

namespace Requirements\Test;

interface RunnerExtension
{
    public function run(RunnerConfig $config, string $target): TestResult;
}
