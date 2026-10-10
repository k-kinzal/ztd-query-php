<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Leaf;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use Override;

/**
 * A value known before any row is read: a literal, or an expression folded to its value.
 *
 * @visibility MySqlMemory
 */
final class Constant implements Evaluable
{
    /**
     * @param Domain $domain The domain of the value
     * @param int|float|string|null $value The value, held as its kind says
     */
    public function __construct(public readonly Domain $domain, public readonly int|float|string|null $value)
    {
    }

    /**
     * Answers the domain of the value.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Answers the value.
     */
    #[Override]
    public function evaluate(Frame $frame): int|float|string|null
    {
        return $this->value;
    }
}
