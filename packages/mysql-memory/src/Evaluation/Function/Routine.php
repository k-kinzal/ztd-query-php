<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function;

use Closure;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;

/**
 * A built-in function: its name, how many arguments it takes, and its body.
 *
 * The body computes the value from the frame, the argument evaluables and the type SQL Semantics
 * resolved for the call.
 *
 * @visibility MySqlMemory
 */
final class Routine
{
    /**
     * @param string $name The function name, in upper case
     * @param int $minimum The fewest arguments
     * @param int $maximum The most arguments, or -1 for no bound
     * @param Closure(Frame, list<Evaluable>, Domain): (int|float|string|null) $body Computes the result
     */
    public function __construct(public readonly string $name, public readonly int $minimum, public readonly int $maximum, public readonly Closure $body)
    {
    }

    /**
     * Tells whether a call with a number of arguments is valid.
     */
    public function accepts(int $count): bool
    {
        return $count >= $this->minimum && ($this->maximum < 0 || $count <= $this->maximum);
    }
}
