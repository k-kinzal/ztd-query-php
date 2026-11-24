<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter;

/**
 * Selects one persistent sequence logging assignment.
 * @visibility public
 * @example Requesting unlogged storage
 *     \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceLogging::Unlogged->value // => 'UNLOGGED'
 */
enum SequenceLogging: string
{
    case Logged = 'LOGGED';
    case Unlogged = 'UNLOGGED';
}
