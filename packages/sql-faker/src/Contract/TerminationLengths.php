<?php

declare(strict_types=1);

namespace SqlFaker\Contract;

use InvalidArgumentException;

final class TerminationLengths
{
    /**
     * @var array<string, int>
     */
    public readonly array $lengths;

    /**
     * @param array<array-key, mixed> $lengths
     */
    public function __construct(array $lengths)
    {
        foreach ($lengths as $ruleName => $length) {
            if (!is_string($ruleName) || $ruleName === '') {
                throw new InvalidArgumentException('Termination lengths must be keyed by non-empty rule names.');
            }

            if (!is_int($length) || $length < 0) {
                throw new InvalidArgumentException('Termination lengths must contain non-negative integer lengths.');
            }
        }

        /** @var array<string, int> $lengths */
        $this->lengths = $lengths;
    }

    public function lengthOf(string $ruleName): int
    {
        if ($ruleName === '') {
            throw new InvalidArgumentException('Rule name must be non-empty.');
        }

        return $this->lengths[$ruleName] ?? 1;
    }
}
