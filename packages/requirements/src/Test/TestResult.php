<?php

declare(strict_types=1);

namespace Requirements\Test;

/**
 * The outcome of running one test target.
 *
 * The status is "passed", "failed", "error" or "unverified"; only "passed" with at least one
 * executed test verifies a specification.
 *
 * @visibility public
 *
 * @example Reporting two passing data-provider cases
 *     (new \Requirements\Test\TestResult('passed', 2))->toArray() // => ['status' => 'passed', 'tests' => 2, 'message' => '']
 */
final class TestResult
{
    /**
     * @param string $status The verdict of the run
     * @param int $tests The number of executed test cases
     * @param string $message Why the run did not pass, or an empty string
     */
    public function __construct(public readonly string $status, public readonly int $tests, public readonly string $message = '')
    {
    }

    /**
     * Returns the result as a JSON-ready record.
     *
     * @return array{status: string, tests: int, message: string} The status, executed tests and message
     */
    public function toArray(): array
    {
        return ['status' => $this->status, 'tests' => $this->tests, 'message' => $this->message];
    }
}
