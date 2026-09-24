<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Control;

/**
 * The transaction effect requested by a SQLite trigger error.
 * @visibility public
 * @example Reading the effect requested by a trigger error
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)');
 *     $trigger = (new \SqlSemantics\Binder($schema))->bind("CREATE TRIGGER tr BEFORE INSERT ON t BEGIN SELECT RAISE(ABORT, 'stop'); END");
 *     $trigger->body->steps[0]->outputs[0]->expression->action->value // => 'ABORT'
 */
enum RaiseAction: string
{
    case Rollback = 'ROLLBACK';
    case Abort = 'ABORT';
    case Fail = 'FAIL';
}
