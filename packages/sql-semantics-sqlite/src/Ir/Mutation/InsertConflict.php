<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Mutation;

/**
 * Whether an INSERT inherits or overrides the target schema's conflict policy.
 *
 * @visibility public
 * @example Choosing a policy for a statement
 *     \SqlSemantics\Platform\Sqlite\Ir\Mutation\InsertConflict::Ignore->value // => 'ignore'
 */
enum InsertConflict: string
{
    case Inherit = 'inherit';
    case Abort = 'abort';
    case Ignore = 'ignore';
    case Fail = 'fail';
    case Rollback = 'rollback';
    case Replace = 'replace';
}
