<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Trigger;

/**
 * A classified change that can invoke a trigger.
 * @visibility public
 */
interface Event
{
    /**
     * Identifies the triggering write operation.
     */
    public function operation(): WriteEvent;
}
