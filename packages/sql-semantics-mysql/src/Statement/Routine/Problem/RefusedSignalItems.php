<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Problem;

use SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedTemporalLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A refused condition-information value and the condition/items that precede it.
 *
 * The prefix supplies name resolution only; it is never executed. Its optional
 * condition and completed items have the same meaning for SIGNAL and RESIGNAL.
 *
 * @visibility public
 * @example Retaining a condition-information prefix
 *     $prefix = new \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal(null);
 *     (new \SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedSignalItems($prefix, new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedTemporalLiteral('bad')))->message() // => 'Incorrect DATE value.'
 */
final class RefusedSignalItems implements Diagnostic
{
    use Snapshot;

    /**
     * @param Resignal $prefix The optional condition and completed information items
     * @param RefusedTemporalLiteral $problem The invalid value following that prefix
     */
    public function __construct(public readonly Resignal $prefix, public readonly RefusedTemporalLiteral $problem)
    {
    }

    /**
     * Describes the refused operand; preceding binding errors remain context-dependent.
     */
    public function message(): string
    {
        return $this->problem->message();
    }
}
