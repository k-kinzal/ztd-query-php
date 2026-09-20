<?php

declare(strict_types=1);

namespace SqlFixture\Plan\Choice;

use SqlFixture\Plan\ColumnRef;
use SqlFixture\Plan\PlanStructureException;

/**
 * A relation choice cannot describe an unambiguous row dependency.
 */
final class ChoiceDefinitionException extends PlanStructureException
{
    /**
     * Identifies the discriminator and the invalid part of its definition.
     */
    public function __construct(ColumnRef $discriminator, string $reason)
    {
        parent::__construct(sprintf('Invalid choice on %s: %s', $discriminator->toString(), $reason));
    }
}
