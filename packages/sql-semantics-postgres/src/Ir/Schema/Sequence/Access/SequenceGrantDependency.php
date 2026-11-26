<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access;

/**
 * The native disposition of grants depending on the grant being revoked.
 * @visibility public
 * @example Propagating the revocation to unsupported dependent grants
 *     \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceGrantDependency::Cascade->value // => 'CASCADE'
 */
enum SequenceGrantDependency: string
{
    case Restrict = 'RESTRICT';
    case Cascade = 'CASCADE';
}
