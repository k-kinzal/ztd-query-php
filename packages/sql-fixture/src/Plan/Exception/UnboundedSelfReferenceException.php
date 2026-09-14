<?php

declare(strict_types=1);

namespace SqlFixture\Plan\Exception;

use SqlFixture\Plan\PlanStructureException;

/**
 * A required self-reference has no terminating case.
 */
final class UnboundedSelfReferenceException extends PlanStructureException
{
    /**
     * A required self-reference has no terminating case.
     */
    public function __construct(
        public readonly string $table,
        public readonly string $written,
    ) {
        parent::__construct(sprintf(
            'The relation %s makes every %s row need another one, without end. Mark the '
            . 'child optional with ? so the chain can stop.',
            $written,
            $table
        ));
    }
}
