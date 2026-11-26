<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Key;

/**
 * The default resolution of a duplicate row key.
 *
 * @visibility public
 * @example Selecting the replacement policy
 *     \SqlSemantics\Platform\Sqlite\Ir\Schema\Key\KeyConflict::Replace->value // => 'REPLACE'
 */
enum KeyConflict: string
{
    case Abort = 'ABORT';
    case Fail = 'FAIL';
    case Ignore = 'IGNORE';
    case Replace = 'REPLACE';
    case Rollback = 'ROLLBACK';
}
