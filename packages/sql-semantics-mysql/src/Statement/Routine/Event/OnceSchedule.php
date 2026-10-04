<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Event;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * The schedule `AT timestamp`: the event runs once.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-event.html.
 *
 * @visibility public
 * @example Reading a one-time schedule
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("CREATE EVENT e ON SCHEDULE AT '2030-01-01 00:00:00' DO SELECT 1");
 *     $create->statement->schedule->at->value() // => '2030-01-01 00:00:00'
 */
final class OnceSchedule implements Schedule
{
    use Snapshot;

    /**
     * @param Scalar $at The time the event runs
     */
    public function __construct(public readonly Scalar $at)
    {
    }

    /**
     * Derives the time expression.
     */
    public function deriveSchedule(Derivation $derivation, Environment $environment): void
    {
        $derivation->scalar($this->at, $environment);
    }

    /**
     * Writes AT and the time.
     */
    public function render(Output $out): void
    {
        $out->keyword('AT')->node($this->at);
    }
}
