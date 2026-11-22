<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Mutation;

/**
 * The conflict resolution algorithms of SQLite.
 *
 * Source: https://sqlite.org/lang_conflict.html.
 *
 * @visibility SqlSemantics
 */
enum ConflictResolution: string
{
    case Rollback = 'ROLLBACK';
    case Abort = 'ABORT';
    case Fail = 'FAIL';
    case Ignore = 'IGNORE';
    case Replace = 'REPLACE';
}
