<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger;

use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * Associates a location in one insertion destination with a checked value.
 *
 * @visibility public
 * @example Writing NULL into the second insertion position
 *     $null = new \SqlSemantics\Platform\Sqlite\Ir\NullLiteral();
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerInsertField(1, $null))->column // => 1
 */
final class TriggerInsertField
{
    use Snapshot;

    /**
     * Checks the column and retains the closed operand for this position.
     * @throws InvalidConstruction When the destination position is negative
     */
    public function __construct(
        public readonly int $column,
        public readonly IntegerLiteral|TextLiteral|NullLiteral|TriggerField $value,
    ) {
        Check::input($column >= 0, 'A trigger insertion needs a nonnegative destination position.');
    }
}
