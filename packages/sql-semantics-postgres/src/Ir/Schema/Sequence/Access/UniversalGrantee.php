<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access;

/**
 * The native universal grantee of an object privilege.
 * @visibility public
 * @example Referring to all roles
 *     \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\UniversalGrantee::Everyone->value // => 'PUBLIC'
 */
enum UniversalGrantee: string
{
    case Everyone = 'PUBLIC';
}
