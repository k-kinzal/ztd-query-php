<?php

declare(strict_types=1);

namespace Requirements\Test;

/**
 * Executes one linked test target and reports what actually ran.
 *
 * Configuration chooses how a runner executes; definitions choose which target it runs. A
 * passing result needs the "passed" status and at least one executed test.
 *
 * @visibility public
 *
 * @example Implementing a runner that knows no targets
 *     $runner = new class () implements \Requirements\Test\RunnerExtension { public function run(\Requirements\Test\RunnerConfig $config, string $target): \Requirements\Test\TestResult { return new \Requirements\Test\TestResult('error', 0, "Unknown target: $target"); } };
 *     $runner->run(new \Requirements\Test\RunnerConfig('custom', ['true'], '/'), 'login')->message // => 'Unknown target: login'
 */
interface RunnerExtension
{
    /**
     * Runs one test target.
     *
     * @param RunnerConfig $config How the runner executes: command, working directory and timeout
     * @param string $target The test selection written in an item's tests entry
     *
     * @return TestResult The status, the number of executed tests and a message on failure
     */
    public function run(RunnerConfig $config, string $target): TestResult;
}
