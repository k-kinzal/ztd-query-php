<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Mutation;

/**
 * The action for an inserted row that violates a uniqueness constraint.
 *
 * @visibility public
 * @example Choosing the action for every uniqueness constraint
 *     \SqlSemantics\Platform\Sqlite\Ir\Mutation\InsertConflictHandler::DoNothing->value // => 'do-nothing'
 */
enum InsertConflictHandler: string
{
    case None = 'none';
    case DoNothing = 'do-nothing';
}
