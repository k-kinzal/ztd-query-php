<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Removal;

/**
 * The native schema verification scope of a guarded trigger absence.
 *
 * @visibility public
 * @example Selecting verification of the complete search path
 *     \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Removal\TriggerVerification::SearchPath->value // => 'search-path'
 */
enum TriggerVerification: string
{
    case SearchPath = 'search-path';
    case KnownSchema = 'known-schema';
    case UnknownSchema = 'unknown-schema';
}
