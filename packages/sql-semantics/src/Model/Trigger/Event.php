<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Trigger;

/**
 * A classified change that can invoke a trigger.
 * @visibility public
 * @example Identifying the write operation of a trigger event
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('CREATE TRIGGER tr AFTER UPDATE OF id ON t BEGIN UPDATE t SET x=new.id WHERE id=old.id; END');
 *     $statement->event instanceof \SqlSemantics\Model\Trigger\Event // => true
 *     $statement->event->operation() // => \SqlSemantics\Model\Trigger\WriteEvent::Update
 */
interface Event
{
    /**
     * Identifies the triggering write operation.
     */
    public function operation(): WriteEvent;
}
