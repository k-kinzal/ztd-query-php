<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Schema\Definition;

/**
 * SQLite's requested response to a constraint violation.
 * @visibility public
 * @example Selecting an explicit violation policy
 *     \SqlSemantics\Statement\Schema\Definition\ConflictAction::Ignore->value // => 'IGNORE'
 */
enum ConflictAction: string
{
    case Implicit = '';
    case Rollback = 'ROLLBACK';
    case Abort = 'ABORT';
    case Fail = 'FAIL';
    case Ignore = 'IGNORE';
    case Replace = 'REPLACE';
}
