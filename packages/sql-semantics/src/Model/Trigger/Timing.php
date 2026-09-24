<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Trigger;

/**
 * The point at which a trigger acts relative to its row operation.
 * @visibility public
 * @example Reading the timing of a trigger
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('CREATE TRIGGER tr INSTEAD OF INSERT ON t BEGIN INSERT INTO t(id) VALUES (new.id); END');
 *     $statement->timing // => \SqlSemantics\Model\Trigger\Timing::InsteadOf
 */
enum Timing: string
{
    case Before = 'BEFORE';
    case After = 'AFTER';
    case InsteadOf = 'INSTEAD OF';
}
