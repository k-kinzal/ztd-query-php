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
 * A scalar conversion to MySQL's unsigned BIGINT result domain.
 *
 * @visibility public
 * @example Giving a NULL projection an unsigned integer type
 *     $input = new \SqlSemantics\Platform\MySql\Ir\NullLiteral();
 *     (new \SqlSemantics\Platform\MySql\Ir\Expression\UnsignedCast($input))->operand === $input // => true
 */
final class UnsignedCast
{
    use Snapshot;

    /**
     * Owns the checked input to the unsigned conversion.
     */
    public function __construct(
        public readonly Arithmetic|BooleanLiteral|ColumnReference|IntegerLiteral|NullLiteral|RangeIntegerLiteral|SignedCast|self $operand,
    ) {
    }
}
