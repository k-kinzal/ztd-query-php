<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Leaf;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;

/**
 * An expression of an enclosing block, evaluated in the frame of that block.
 *
 * @visibility MySqlMemory
 */
final class Outer implements Evaluable
{
    /**
     * @param Evaluable $inner The expression of the enclosing block
     * @param int $depth The number of blocks out
     */
    public function __construct(public readonly Evaluable $inner, public readonly int $depth)
    {
    }

    /**
     * Answers the domain of the expression.
     */
    #[\Override]
    public function domain(): Domain
    {
        return $this->inner->domain();
    }

    /**
     * Evaluates the expression in the frame of its block.
     */
    #[\Override]
    public function evaluate(Frame $frame): int|float|string|null
    {
        return $this->inner->evaluate($frame->out($this->depth));
    }
}
