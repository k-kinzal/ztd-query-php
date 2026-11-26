<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Predicate;

use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\IntegralColumnReference;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * An inclusive MySQL range test in the signed integer comparison domain.
 *
 * @visibility public
 * @example Checking whether an integer falls between two inclusive bounds
 *     $test = new \SqlSemantics\Platform\MySql\Ir\Predicate\IntegerRange(new \SqlSemantics\Platform\MySql\Ir\ColumnReference(0), new \SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral('1'), new \SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral('3'));
 *     $test->upper->decimal // => '3'
 */
final class IntegerRange
{
    use Snapshot;

    /**
     * Holds the subject and both bounds in their written roles.
     */
    public function __construct(
        public readonly ColumnReference|IntegralColumnReference|RangeIntegerLiteral|NullLiteral $subject,
        public readonly ColumnReference|IntegralColumnReference|RangeIntegerLiteral|NullLiteral $lower,
        public readonly ColumnReference|IntegralColumnReference|RangeIntegerLiteral|NullLiteral $upper,
        public readonly bool $negated = false,
    ) {
    }
}
