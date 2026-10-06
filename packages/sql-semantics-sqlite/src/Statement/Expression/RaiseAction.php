<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression;

/**
 * What a RAISE function does to the statement that fired the trigger.
 *
 * Source: https://sqlite.org/lang_createtrigger.html#the_raise_function.
 *
 * @visibility public
 * @example Reading the action of a RAISE
 *     $trigger = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze("CREATE TRIGGER r BEFORE DELETE ON t BEGIN SELECT RAISE(ABORT, 'no'); END");
 *     $trigger->statement->steps[0]->columns[0]->expression->action // => \SqlSemantics\Platform\Sqlite\Statement\Expression\RaiseAction::Abort
 */
enum RaiseAction: string
{
    case Ignore = 'IGNORE';
    case Rollback = 'ROLLBACK';
    case Abort = 'ABORT';
    case Fail = 'FAIL';
}
