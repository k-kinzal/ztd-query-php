<?php

declare(strict_types=1);

namespace SqlFixture\Plan\Exception;

use SqlFixture\Plan\PlanSyntaxException;

/**
 * A fixture plan closes a bracket without an opening bracket.
 */
final class UnbalancedBracketsException extends PlanSyntaxException
{
    /**
     * A fixture plan closes a bracket without an opening bracket.
     */
    public function __construct(
        public readonly string $plan,
    ) {
        parent::__construct(sprintf(
            'The fixture plan closes a bracket it never opened. Plan: %s',
            $plan
        ));
    }
}
