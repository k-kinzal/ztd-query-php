<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Subquery;

/**
 * A retained projection value and the NULL state observed by its comparison.
 *
 * Legacy empty projections can expose an initial NULL state before evaluating their source.
 * Keeping that state separate avoids substituting a fabricated scalar for a NULL result.
 *
 * @visibility MySqlMemory\Evaluation
 */
final class ProjectionRead
{
    /**
     * The NULL state observed by this comparison, independently of the retained scalar.
     */
    public readonly bool $isNull;

    /**
     * @param int|float|string|\MySqlMemory\Evaluation\ExactDecimal|null $value The expression's actual value
     * @param bool|null $isNull The observed NULL state, or null to use the value's state
     */
    public function __construct(public readonly int|float|string|\MySqlMemory\Evaluation\ExactDecimal|null $value, ?bool $isNull = null)
    {
        $this->isNull = $isNull ?? $value === null;
    }
}
