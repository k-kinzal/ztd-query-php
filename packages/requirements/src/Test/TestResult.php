<?php

declare(strict_types=1);

namespace Requirements\Test;

final class TestResult
{
    public function __construct(public readonly string $status, public readonly int $tests, public readonly string $message = '')
    {
    }

    /** @return array{status: string, tests: int, message: string} */
    public function toArray(): array
    {
        return ['status' => $this->status, 'tests' => $this->tests, 'message' => $this->message];
    }
}
