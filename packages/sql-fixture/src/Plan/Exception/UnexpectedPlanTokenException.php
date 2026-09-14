<?php

declare(strict_types=1);

namespace SqlFixture\Plan\Exception;

use SqlFixture\Plan\PlanSyntaxException;

/**
 * A fixture plan token does not match the expected syntax.
 */
final class UnexpectedPlanTokenException extends PlanSyntaxException
{
    /**
     * A fixture plan token does not match the expected syntax.
     */
    public function __construct(
        public readonly string $plan,
        public readonly int $offset,
        public readonly string $expected,
    ) {
        parent::__construct(sprintf(
            'Cannot parse the fixture plan at offset %d: expected %s. Plan: %s',
            $offset,
            $expected,
            $plan
        ));
    }
}
