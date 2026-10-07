<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use Override;

/**
 * A call of a built-in function.
 *
 * @visibility MySqlMemory
 */
final class Call implements Evaluable
{
    /**
     * @param Routine $routine The function
     * @param list<Evaluable> $arguments The arguments
     * @param Domain $domain The domain of the result
     */
    public function __construct(public readonly Routine $routine, public readonly array $arguments, public readonly Domain $domain)
    {
    }

    /**
     * Answers the domain of the result.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Computes the result for a row.
     */
    #[Override]
    public function evaluate(Frame $frame): int|float|string|null
    {
        return ($this->routine->body)($frame, $this->arguments, $this->domain);
    }
}
