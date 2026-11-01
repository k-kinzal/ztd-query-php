<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Window;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\Assignment;
use MySqlMemory\Typing\Domain;
use Override;

/**
 * Carries an assignment whose stored value is published only when its row is emitted.
 *
 * Projection can read the value while sorting or discarding rows for OFFSET.
 * The final projection publishes it after LIMIT, in select-list order alongside
 * other assignments. Verified through subsequent variable reads on MySQL 8.0.
 *
 * @visibility MySqlMemory
 */
final class EmissionAssignment implements Evaluable
{
    /**
     * @param Assignment $assignment The variable and its already evaluated input field
     */
    public function __construct(public readonly Assignment $assignment)
    {
    }

    /**
     * Answers the assignment's output domain.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->assignment->domain();
    }

    /**
     * Reads the stored result without publishing it before row emission.
     */
    #[Override]
    public function evaluate(Frame $frame): int|float|string|\MySqlMemory\Evaluation\ExactDecimal|null
    {
        return $this->assignment->value->evaluate($frame);
    }
}
