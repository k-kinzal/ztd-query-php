<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Mutation;

/**
 * The conflict resolution algorithms of SQLite.
 *
 * Source: https://sqlite.org/lang_conflict.html.
 *
 * @visibility public
 * @example Reading the algorithm of an INSERT OR
 *     $insert = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('INSERT OR IGNORE INTO t VALUES (1)');
 *     $insert->statement->into->resolution // => \SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictResolution::Ignore
 */
enum ConflictResolution: string
{
    case Rollback = 'ROLLBACK';
    case Abort = 'ABORT';
    case Fail = 'FAIL';
    case Ignore = 'IGNORE';
    case Replace = 'REPLACE';
}
