<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression;

/**
 * What a RAISE function does to the statement that fired the trigger.
 *
 * Source: https://sqlite.org/lang_createtrigger.html#the_raise_function.
 *
 * @visibility SqlSemantics
 */
enum RaiseAction: string
{
    case Ignore = 'IGNORE';
    case Rollback = 'ROLLBACK';
    case Abort = 'ABORT';
    case Fail = 'FAIL';
}
