<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Math;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use Override;

/**
 * The first argument of a call that counts the rows it is evaluated for, which its warnings name.
 *
 * It reads as the argument it wraps; the count is kept for the statement.
 *
 * @visibility MySqlMemory
 */
final class Tally implements Evaluable
{
    /**
     * @param Evaluable $argument The argument it reads as
     */
    public function __construct(public readonly Evaluable $argument)
    {
    }

    /**
     * Answers the domain of the argument.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->argument->domain();
    }

    /**
     * Reads the argument, counting the row.
     */
    #[Override]
    public function evaluate(Frame $frame): int|float|string|null
    {
        $kept = $frame->context->kept;
        $kept[$this] = [(int) ($kept[$this][0] ?? 0) + 1];

        return $this->argument->evaluate($frame);
    }

    /**
     * Answers the number of the row the call is evaluated for, from 1.
     */
    public function row(Frame $frame): int
    {
        return max(1, (int) ($frame->context->kept[$this][0] ?? 1));
    }
}
