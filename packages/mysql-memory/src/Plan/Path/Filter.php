<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Path;

use MySqlMemory\Evaluation\Evaluable;
use Override;

/**
 * Keeps the rows of its input for which a condition is true.
 *
 * @visibility MySqlMemory
 */
final class Filter implements AccessPath
{
    /**
     * @param AccessPath $input The rows filtered
     * @param Evaluable $condition The condition, evaluated over each row
     */
    public function __construct(public readonly AccessPath $input, public readonly Evaluable $condition)
    {
    }

    /**
     * Answers the width of the input.
     */
    #[Override]
    public function width(): int
    {
        return $this->input->width();
    }
}
