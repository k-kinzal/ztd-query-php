<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Event;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * The schedule `AT timestamp`: the event runs once.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-event.html.
 *
 * @visibility SqlSemantics
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
