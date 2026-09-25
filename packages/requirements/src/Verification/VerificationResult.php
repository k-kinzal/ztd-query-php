<?php

declare(strict_types=1);

namespace Requirements\Verification;

/**
 * The verdict for one item: its status and how many linked targets and tests passed.
 *
 * The status is "passed", "failed", "unverified", "unsupported", "not-applicable" or "not-run".
 */
final class VerificationResult
{
    /**
     * @param string $status The verdict
     * @param int $tests The number of executed test cases
     * @param int|null $passedTargets The number of passing linked targets, null when tests were not run
     * @param int $totalTargets The number of distinct linked targets
     * @param string $message The failure messages of the targets, one per line
     */
    public function __construct(
        public readonly string $status,
        public readonly int $tests,
        public readonly ?int $passedTargets,
        public readonly int $totalTargets,
        public readonly string $message = '',
    ) {
    }

    /**
     * Returns the verdict as a JSON-ready record.
     *
     * @return array{status: string, tests: int, passed_targets: ?int, total_targets: int, message: string} The verdict fields
     */
    public function toArray(): array
    {
        return ['status' => $this->status, 'tests' => $this->tests, 'passed_targets' => $this->passedTargets, 'total_targets' => $this->totalTargets, 'message' => $this->message];
    }
}
