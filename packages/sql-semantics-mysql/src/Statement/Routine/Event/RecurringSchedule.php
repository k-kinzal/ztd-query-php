<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Event;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * The schedule `EVERY quantity unit [STARTS timestamp] [ENDS timestamp]`: the event runs repeatedly.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-event.html.
 *
 * @visibility public
 * @example Reading a recurring schedule
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE EVENT e ON SCHEDULE EVERY 2 HOUR STARTS CURRENT_TIMESTAMP DO SELECT 1');
 *     [$create->statement->schedule->unit, $create->statement->schedule->ends] // => [\SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit::Hour, null]
 */
final class RecurringSchedule implements Schedule
{
    use Snapshot;

    /**
     * @param Scalar $quantity The length of the interval between two runs
     * @param IntervalUnit $unit The unit of the interval
     * @param Scalar|null $starts The time of the first run, when written
     * @param Scalar|null $ends The time after which the event no longer runs, when written
     */
    public function __construct(public readonly Scalar $quantity, public readonly IntervalUnit $unit, public readonly ?Scalar $starts = null, public readonly ?Scalar $ends = null)
    {
    }

    /**
     * Derives the quantity and the time expressions.
     */
    public function deriveSchedule(Derivation $derivation, Environment $environment): void
    {
        $derivation->scalar($this->quantity, $environment);
        if ($this->starts !== null) {
            $derivation->scalar($this->starts, $environment);
        }
        if ($this->ends !== null) {
            $derivation->scalar($this->ends, $environment);
        }
    }

    /**
     * Writes EVERY, the interval and the STARTS and ENDS clauses.
     */
    public function render(Output $out): void
    {
        $out->keyword('EVERY')->node($this->quantity)->keyword($this->unit->value);
        if ($this->starts !== null) {
            $out->keyword('STARTS')->node($this->starts);
        }
        if ($this->ends !== null) {
            $out->keyword('ENDS')->node($this->ends);
        }
    }
}
