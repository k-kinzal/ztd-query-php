<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Weight;

use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One collation level of the LEVEL clause of WEIGHT_STRING in MySQL 5.6 and 5.7, with its order and reversal flags.
 *
 * Source: https://dev.mysql.com/doc/refman/5.7/en/string-functions.html#function_weight-string.
 *
 * @visibility public
 * @example Holding a descending, reversed level
 *     $level = new \SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightLevel(new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('2'), \SqlSemantics\Platform\MySql\Statement\Query\Direction::Descending, true);
 *     [$level->number->text, $level->direction, $level->reverse] // => ['2', \SqlSemantics\Platform\MySql\Statement\Query\Direction::Descending, true]
 */
final class WeightLevel implements Node
{
    use Snapshot;

    /**
     * @param Numeral $number The level
     * @param Direction|null $direction The written ASC or DESC
     * @param bool $reverse Whether REVERSE is written
     */
    public function __construct(public readonly Numeral $number, public readonly ?Direction $direction = null, public readonly bool $reverse = false)
    {
    }

    /**
     * Writes the level and its flags.
     */
    public function render(Output $out): void
    {
        $out->node($this->number);
        if ($this->direction !== null) {
            $out->keyword($this->direction->value);
        }
        if ($this->reverse) {
            $out->keyword('REVERSE');
        }
    }
}
