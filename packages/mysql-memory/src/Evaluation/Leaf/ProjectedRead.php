<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Leaf;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use Override;

/**
 * Reads an output already evaluated for this row, preserving its effects exactly once.
 *
 * @visibility MySqlMemory
 */
final class ProjectedRead implements Evaluable
{
    /**
     * @param Domain $domain The selected expression's result domain
     * @param int $position Its position in the projection
     */
    public function __construct(public readonly Domain $domain, public readonly int $position)
    {
    }

    /**
     * Answers the selected expression's domain.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Reads the computed field rather than evaluating its expression again.
     *
     * @phpstan-pure
     */
    #[Override]
    public function evaluate(Frame $frame): int|float|string|null
    {
        return $frame->projected[$this->position] ?? null;
    }
}
