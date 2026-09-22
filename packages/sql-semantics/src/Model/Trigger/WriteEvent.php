<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Trigger;

use Override;

/**
 * A trigger on all inserts, updates or deletions.
 * @visibility public
 */
enum WriteEvent: string implements Event
{
    case Insert = 'INSERT';
    case Update = 'UPDATE';
    case Delete = 'DELETE';

    #[Override]
    public function operation(): self
    {
        return $this;
    }
}
