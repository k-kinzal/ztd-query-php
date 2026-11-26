<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema;

/**
 * The default conflict algorithm of an ordinary NOT NULL column constraint.
 *
 * @visibility public
 * @example Declaring that writes should ignore rows with NULL
 *     \SqlSemantics\Platform\Sqlite\Ir\Schema\NotNullConflict::Ignore->value // => 'IGNORE'
 */
enum NotNullConflict: string
{
    case Abort = 'ABORT';
    case Fail = 'FAIL';
    case Ignore = 'IGNORE';
    case Replace = 'REPLACE';
    case Rollback = 'ROLLBACK';
}
