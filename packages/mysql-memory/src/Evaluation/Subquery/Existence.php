<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Subquery;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;

/**
 * EXISTS: 1 when the subquery has a row, else 0.
 *
 * @visibility MySqlMemory
 */
final class Existence implements Evaluable
{
    /**
     * @param Rows $rows The rows of the subquery
     * @param Domain $domain The domain of the truth value
     */
    public function __construct(public readonly Rows $rows, public readonly Domain $domain)
    {
    }

    /**
     * Answers the domain of the truth value.
     */
    #[\Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Tests the subquery for a row.
     */
    #[\Override]
    public function evaluate(Frame $frame): int
    {
        return $this->rows->start($frame)->read() === null ? 0 : 1;
    }
}
