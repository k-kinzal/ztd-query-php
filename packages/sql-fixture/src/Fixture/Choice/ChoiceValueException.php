<?php

declare(strict_types=1);

namespace SqlFixture\Fixture\Choice;

use RuntimeException;
use SqlFixture\Plan\ColumnRef;

/**
 * Row values cannot satisfy the selected relation choice.
 */
final class ChoiceValueException extends RuntimeException
{
    /**
     * Reports the discriminator and the incompatible row requirement.
     */
    public function __construct(ColumnRef $discriminator, string $reason)
    {
        parent::__construct(sprintf('Cannot resolve choice on %s: %s', $discriminator->toString(), $reason));
    }
}
