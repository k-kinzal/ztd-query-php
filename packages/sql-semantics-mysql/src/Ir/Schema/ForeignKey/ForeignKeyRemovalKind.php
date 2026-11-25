<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey;

/**
 * The native meaning of one named foreign-key removal clause.
 * @visibility public
 * @example Distinguishing a legacy session-local clause
 *     \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemovalKind::IgnoredOnTemporary->value // => 'ignored-on-temporary'
 */
enum ForeignKeyRemovalKind: string
{
    case Constraint = 'constraint';
    case IgnoredOnTemporary = 'ignored-on-temporary';
}
