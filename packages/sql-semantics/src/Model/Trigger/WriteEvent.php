<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Trigger;

use Override;

/**
 * A trigger on all inserts, updates or deletions.
 * @visibility public
 * @example Reading the event of a trigger
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('CREATE TRIGGER tr AFTER DELETE ON t BEGIN DELETE FROM t WHERE id=old.id; END');
 *     $statement->event // => \SqlSemantics\Model\Trigger\WriteEvent::Delete
 *     $statement->event->operation() // => \SqlSemantics\Model\Trigger\WriteEvent::Delete
 */
enum WriteEvent: string implements Event
{
    case Insert = 'INSERT';
    case Update = 'UPDATE';
    case Delete = 'DELETE';

    /**
     * Returns the write operation that activates this trigger event.
     */
    #[Override]
    public function operation(): self
    {
        return $this;
    }
}
