<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Program;

use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerCondition;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerField;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A source-free trigger action that conditionally interrupts native execution.
 *
 * @visibility public
 * @example Skipping later trigger actions without an error
 *     $policy = \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Program\TriggerRaisePolicy::Ignore;
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Program\TriggerRaise($policy))->message // => null
 */
final class TriggerRaise
{
    use Snapshot;

    /**
     * Checks the message operand against the chosen native effect.
     */
    public function __construct(
        public readonly TriggerRaisePolicy $policy,
        public readonly TriggerField|IntegerLiteral|TextLiteral|NullLiteral|null $message = null,
        public readonly ?TriggerCondition $where = null,
    ) {
        Check::input(($policy === TriggerRaisePolicy::Ignore) === ($message === null), 'A trigger RAISE needs a message for every policy except IGNORE.');
    }
}
