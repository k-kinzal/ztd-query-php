<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Text;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * One item of the MySQL 5.x `LEVEL` clause of WEIGHT_STRING: a collation level from 1 to 6, optionally descending
 * and reversed, or a range of levels without modifiers.
 * @visibility public
 * @example Reading a weight level
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build();
 *     $query = (new \SqlSemantics\Binder($schema))->bind("SELECT WEIGHT_STRING('ab' LEVEL 2 DESC)");
 *     $level = $query->outputs[0]->expression->levels[0];
 *     [$level->first, $level->last, $level->descending, $level->reverse] // => [2, 2, true, false]
 */
final class WeightLevel
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly int $first, public readonly int $last, public readonly bool $descending = false, public readonly bool $reverse = false)
    {
        if ($first < 1 || $last > 6 || $first > $last) {
            throw new InvalidStructure('Weight levels are ordered numbers from 1 to 6.');
        }
        if ($first !== $last && ($descending || $reverse)) {
            throw new InvalidStructure('A range of weight levels has no DESC or REVERSE modifier.');
        }
    }
}
