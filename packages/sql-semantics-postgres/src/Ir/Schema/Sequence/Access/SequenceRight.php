<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access;

/**
 * A native sequence privilege or the legacy no-op token.
 * @visibility public
 * @example Requesting the usage privilege
 *     \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceRight::Usage->value // => 'USAGE'
 */
enum SequenceRight: string
{
    case Select = 'SELECT';
    case Update = 'UPDATE';
    case Usage = 'USAGE';
    case LegacyRule = 'RULE';
}
