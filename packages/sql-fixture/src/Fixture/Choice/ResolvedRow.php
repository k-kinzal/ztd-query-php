<?php

declare(strict_types=1);

namespace SqlFixture\Fixture\Choice;

use SqlFixture\Plan\FixturePlan;
use SqlFixture\Plan\Relation;

/**
 * Carries the selected plan and fixed values into one row generation scope.
 * @visibility root
 * @template TValue = mixed
 */
final class ResolvedRow
{
    /**
     * @param array<TValue> $values
     */
    public function __construct(
        public readonly FixturePlan $plan,
        public readonly array $values,
        public readonly ?Relation $arrivedBy,
    ) {
    }
}
