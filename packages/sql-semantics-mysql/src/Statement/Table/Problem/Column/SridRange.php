<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Problem\Column;

use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A column restriction whose identifier exceeds the unsigned 32-bit domain.
 *
 * This token condition precedes column-type compatibility and catalog lookup.
 *
 * @visibility public
 * @example Describing an out-of-range column restriction
 *     (new \SqlSemantics\Platform\MySql\Statement\Table\Problem\Column\SridRange(new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('4294967296')))->message() // => "SRID value is out of range in 'SRID'"
 */
final class SridRange implements Diagnostic
{
    use Snapshot;

    /**
     * @param Numeral $srid The complete numeric operand
     */
    public function __construct(public readonly Numeral $srid)
    {
    }

    /**
     * Describes the intrinsic restriction on a column's system identifier.
     */
    public function message(): string
    {
        return "SRID value is out of range in 'SRID'";
    }
}
