<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Expression;

use SqlSemantics\Platform\MySql\Ir\Arithmetic;
use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A scalar conversion to MySQL's signed BIGINT result domain.
 *
 * @visibility public
 * @example Giving a NULL projection a signed integer type
 *     $input = new \SqlSemantics\Platform\MySql\Ir\NullLiteral();
 *     (new \SqlSemantics\Platform\MySql\Ir\Expression\SignedCast($input))->operand === $input // => true
 */
final class SignedCast
{
    use Snapshot;

    /**
     * Owns the checked input to the signed conversion.
     */
    public function __construct(
        public readonly Arithmetic|BooleanLiteral|ColumnReference|IntegerLiteral|NullLiteral|RangeIntegerLiteral|UnsignedCast|self $operand,
    ) {
    }
}
