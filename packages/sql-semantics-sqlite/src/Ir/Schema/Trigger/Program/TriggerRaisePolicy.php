<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Program;

/**
 * The native effect of executing a SQLite trigger RAISE.
 *
 * @visibility public
 * @example Select a policy that rolls back the current statement
 *     \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Program\TriggerRaisePolicy::Abort->value // => 'ABORT'
 */
enum TriggerRaisePolicy: string
{
    case Ignore = 'IGNORE';
    case Fail = 'FAIL';
    case Abort = 'ABORT';
    case Rollback = 'ROLLBACK';
}
