<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter;

/**
 * Remove the existing sequence ownership edge.
 * @visibility public
 * @example Remove the owning column dependency
 *     \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\ClearSequenceOwner::Remove->value // => 'remove'
 */
enum ClearSequenceOwner: string
{
    case Remove = 'remove';
}
