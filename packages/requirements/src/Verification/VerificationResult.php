<?php

declare(strict_types=1);

namespace Requirements\Verification;

final class VerificationResult
{
    public function __construct(
        public readonly string $status,
        public readonly int $tests,
        public readonly ?int $passedTargets,
        public readonly int $totalTargets,
        public readonly string $message = '',
    ) {
    }

    /** @return array{status: string, tests: int, passed_targets: ?int, total_targets: int, message: string} */
    public function toArray(): array
    {
        return ['status' => $this->status, 'tests' => $this->tests, 'passed_targets' => $this->passedTargets, 'total_targets' => $this->totalTargets, 'message' => $this->message];
    }
}
