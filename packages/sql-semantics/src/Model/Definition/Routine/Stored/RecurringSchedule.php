<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Stored;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Temporal\MySqlUnit;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Runs an event repeatedly at an interval, optionally within a STARTS and ENDS window.
 * @visibility public
 * @example Reading a recurring schedule
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE EVENT e ON SCHEDULE EVERY 2 HOUR DO DO 1');
 *     $statement->schedule->unit === \SqlSemantics\Model\Scalar\Temporal\MySqlUnit::Hour // => true
 *     $statement->schedule->starts // => null
 */
final class RecurringSchedule
{
    /**
     * Requires MySQL expressions and an interval unit coarser than microseconds.
     * @throws InvalidStructure
     */
    public function __construct(public readonly Expression $every, public readonly MySqlUnit $unit, public readonly ?Expression $starts = null, public readonly ?Expression $ends = null)
    {
        foreach ([$every, $starts, $ends] as $expression) {
            if ($expression !== null && $expression->type->dialect !== Dialect::MySql) {
                throw new InvalidStructure('An event schedule requires MySQL expressions.');
            }
        }
        if (str_contains($unit->value, 'MICROSECOND')) {
            throw new InvalidStructure('An event interval cannot be measured in microseconds.');
        }
    }
}
