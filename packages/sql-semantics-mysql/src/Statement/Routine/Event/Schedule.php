<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Event;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Node;

/**
 * When an event runs: once at a time, or repeatedly at an interval.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-event.html.
 *
 * @visibility SqlSemantics
 */
interface Schedule extends Node
{
    /**
     * Derives the expressions of the schedule in the scope of the statement: inside a stored program they see its variables.
     */
    public function deriveSchedule(Derivation $derivation, Environment $environment): void;
}
