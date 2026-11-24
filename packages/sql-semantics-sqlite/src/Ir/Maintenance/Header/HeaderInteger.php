<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Header;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A normalized signed integer written to a native database header field.
 *
 * @visibility public
 * @example Fixing a native cookie operand
 *     $value = new \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Header\HeaderInteger(-7);
 *     $value->value // => -7
 */
final class HeaderInteger
{
    use Snapshot;

    /**
     * Stores the signed 32-bit value of the write.
     * @throws InvalidConstruction When the operand falls outside the native signed domain
     */
    public function __construct(public readonly int $value)
    {
        Check::input($value >= -2147483648 && $value <= 2147483647, 'A header pragma write requires a signed 32-bit integer.');
    }
}
