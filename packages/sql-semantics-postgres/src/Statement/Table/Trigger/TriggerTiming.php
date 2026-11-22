<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger;

/**
 * When a trigger fires relative to the event.
 *
 * Mirrors `TRIGGER_TYPE_BEFORE`, `TRIGGER_TYPE_AFTER` and `TRIGGER_TYPE_INSTEAD`.
 * Source: https://www.postgresql.org/docs/17/sql-createtrigger.html.
 *
 * @visibility SqlSemantics
 */
enum TriggerTiming: string
{
    case Before = 'BEFORE';
    case After = 'AFTER';
    case InsteadOf = 'INSTEAD OF';

    /**
     * Answers the keywords.
     *
     * @return list<string>
     */
    public function keywords(): array
    {
        return explode(' ', $this->value);
    }
}
