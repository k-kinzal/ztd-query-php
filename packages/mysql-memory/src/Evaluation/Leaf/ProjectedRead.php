<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Leaf;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use Override;

/**
 * Reads the latest stored output field without repeating its expression effects.
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
     * Reads the last computed field, including the previous row before projection updates it.
     *
     * @phpstan-pure
     */
    #[Override]
    public function evaluate(Frame $frame): int|float|string|\MySqlMemory\Evaluation\ExactDecimal|null
    {
        return $frame->projected[$this->position] ?? null;
    }
}
