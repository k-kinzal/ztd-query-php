<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Path\Transform;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Plan\Path\AccessPath;
use Override;

/**
 * Keeps the rows of its input for which a condition is true.
 *
 * A precondition constant for the statement is evaluated once, before the input is read; when it
 * is not true, no row of the input is read.
 *
 * @visibility MySqlMemory
 */
final class Filter implements AccessPath
{
    /**
     * @param AccessPath $input The rows filtered
     * @param Evaluable $condition The condition, evaluated over each row
     * @param Evaluable|null $precondition The part of the condition constant for the statement, or null for none
     * @param Evaluable|null $recheck The constant-table conjuncts checked again after the complete row predicate passes
     */
    public function __construct(public readonly AccessPath $input, public readonly Evaluable $condition, public readonly ?Evaluable $precondition = null, public readonly ?Evaluable $recheck = null)
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
