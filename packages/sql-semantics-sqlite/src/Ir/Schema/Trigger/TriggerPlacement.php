<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger;

/**
 * The storage and native creation route of a trigger.
 *
 * @visibility public
 * @example Identifying a connection-local trigger
 *     \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerPlacement::NamedTemporary->value // => 'named-temporary'
 */
enum TriggerPlacement: string
{
    case Stored = 'stored';
    case NamedTemporary = 'named-temporary';
    case TemporaryKeyword = 'temporary-keyword';
}
