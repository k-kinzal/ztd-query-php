<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Mutation;

use SqlSemantics\Platform\Sqlite\Ir\Arithmetic;
use SqlSemantics\Platform\Sqlite\Ir\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\HexIntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Ir\RealLiteral;
use SqlSemantics\Platform\Sqlite\Ir\TextLiteral;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A scalar value written to a local target column slot.
 *
 * @visibility public
 * @example Assigning a value to the first target slot
 *     $value = new \SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral('5');
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Mutation\UpdateAssignment(0, $value))->targetSlot // => 0
 */
final class UpdateAssignment
{
    use Snapshot;

    /**
     * Identifies the target slot and the scalar value expression.
     */
    public function __construct(
        public readonly int $targetSlot,
        public readonly Arithmetic|BlobLiteral|ColumnReference|Comparison|HexIntegerLiteral|IntegerLiteral|Logical|NullLiteral|Parameter|RealLiteral|TextLiteral $value,
    ) {
        Check::input($targetSlot >= 0, 'An assignment slot must be nonnegative.');
    }
}
