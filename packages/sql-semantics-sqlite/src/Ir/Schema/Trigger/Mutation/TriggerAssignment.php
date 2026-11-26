<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation;

use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerField;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A checked scalar assignment in one trigger UPDATE action.
 *
 * @visibility public
 * @example Assigning NULL to the first position
 *     $assignment = new \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerAssignment(0, new \SqlSemantics\Platform\Sqlite\Ir\NullLiteral());
 *     $assignment->column // => 0
 */
final class TriggerAssignment
{
    use Snapshot;

    /**
     * Checks the write position independently of the enclosing table.
     */
    public function __construct(
        public readonly int $column,
        public readonly IntegerLiteral|TextLiteral|NullLiteral|TriggerTargetField|TriggerField $value,
    ) {
        Check::input($column >= 0, 'A trigger assignment position cannot be negative.');
    }
}
