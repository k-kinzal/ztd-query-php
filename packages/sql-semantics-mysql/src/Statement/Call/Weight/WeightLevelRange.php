<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Weight;

use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * A range of collation levels `from - to` in the LEVEL clause of WEIGHT_STRING in MySQL 5.6 and 5.7.
 *
 * Source: https://dev.mysql.com/doc/refman/5.7/en/string-functions.html#function_weight-string.
 *
 * @visibility public
 * @example Holding a range of levels
 *     $range = new \SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightLevelRange(new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('3'));
 *     [$range->from->text, $range->to->text] // => ['1', '3']
 */
final class WeightLevelRange implements Node
{
    use Snapshot;

    /**
     * @param Numeral $from The first level
     * @param Numeral $to The last level
     */
    public function __construct(public readonly Numeral $from, public readonly Numeral $to)
    {
    }

    /**
     * Writes the range.
     */
    public function render(Output $out): void
    {
        $out->node($this->from)->symbol('-')->node($this->to);
    }
}
