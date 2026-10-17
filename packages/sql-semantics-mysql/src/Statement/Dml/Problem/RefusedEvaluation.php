<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Problem;

use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredEvaluation;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A standalone DO refusal retaining every operand for dependency resolution.
 *
 * This diagnostic carries semantic values, never SQL text or parser nodes.
 * Its stored instruction is outside the ordinary statement and scalar domains.
 *
 * @visibility public
 * @example Retaining a refusal without making it a statement
 *     $instruction = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredEvaluation([new \SqlSemantics\Platform\MySql\Statement\Query\Star()]);
 *     (new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedEvaluation($instruction))->message() // => 'No tables used'
 */
final class RefusedEvaluation implements Diagnostic
{
    use Snapshot;

    /**
     * @param DeferredEvaluation $evaluation The complete instruction dependencies
     */
    public function __construct(public readonly DeferredEvaluation $evaluation)
    {
    }

    /**
     * Describes the operand constraint; dependency conditions can precede it.
     */
    public function message(): string
    {
        return $this->evaluation->problem()->message();
    }
}
