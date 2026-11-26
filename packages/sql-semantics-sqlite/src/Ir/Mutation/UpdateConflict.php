<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Mutation;

/**
 * How a SQLite UPDATE handles a constraint conflict.
 *
 * @visibility public
 * @example Choosing an explicit update policy
 *     \SqlSemantics\Platform\Sqlite\Ir\Mutation\UpdateConflict::Abort->value // => 'abort'
 */
enum UpdateConflict: string
{
    case Inherit = 'inherit';
    case Abort = 'abort';
    case Ignore = 'ignore';
    case Fail = 'fail';
    case Rollback = 'rollback';
    case Replace = 'replace';
}
