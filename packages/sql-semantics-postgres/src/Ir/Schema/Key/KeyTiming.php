<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Key;

/**
 * The native check timing of an ordinary PostgreSQL primary key.
 *
 * @visibility public
 * @example Reading the initially deferred timing policy
 *     \SqlSemantics\Platform\PostgreSql\Ir\Schema\Key\KeyTiming::InitiallyDeferred->value // => 'DEFERRABLE INITIALLY DEFERRED'
 */
enum KeyTiming: string
{
    case NotDeferrable = 'NOT DEFERRABLE';
    case InitiallyImmediate = 'DEFERRABLE INITIALLY IMMEDIATE';
    case InitiallyDeferred = 'DEFERRABLE INITIALLY DEFERRED';
}
