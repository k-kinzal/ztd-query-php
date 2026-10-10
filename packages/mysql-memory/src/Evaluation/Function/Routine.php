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
 * The body computes the value from the frame, the argument evaluables, the type SQL Semantics
 * resolved for the call and the text of the call, which messages quote.
 *
 * @visibility MySqlMemory
 */
final class Routine
{
    /**
     * @param string $name The function name, in upper case
     * @param int $minimum The fewest arguments
     * @param int $maximum The most arguments, or -1 for no bound
     * @param Closure(Frame, list<Evaluable>, Domain, string): (int|float|string|null) $body Computes the result
     * @param int $step The number of arguments taken together after the fewest, as the pairs of JSON_OBJECT()
     * @param (Closure(Frame, list<Evaluable>, list<bool>): list<Evaluable>)|null $resolve Runs once when the call is compiled, given whether each argument is known then, and answers the arguments the body reads
     * @param bool $settled Whether resolve counts an argument that stays the same for the statement, as a variable or a parameter, as known
     */
    public function __construct(public readonly string $name, public readonly int $minimum, public readonly int $maximum, public readonly Closure $body, public readonly int $step = 1, public readonly ?Closure $resolve = null, public readonly bool $settled = false)
    {
    }

    /**
     * Tells whether a call with a number of arguments is valid.
     */
    public function accepts(int $count): bool
    {
        return $count >= $this->minimum && ($this->maximum < 0 || $count <= $this->maximum) && ($count - $this->minimum) % $this->step === 0;
    }
}
