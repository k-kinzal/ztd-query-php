<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Storage;

/**
 * Selects the access mode a legacy MySQL tablespace is switched to.
 * @visibility public
 * @example Naming the unavailable mode
 *     \SqlSemantics\Model\Definition\Storage\TablespaceAccess::NotAccessible->value // => 'NOT ACCESSIBLE'
 */
enum TablespaceAccess: string
{
    case ReadOnly = 'READ_ONLY';
    case ReadWrite = 'READ_WRITE';
    case NotAccessible = 'NOT ACCESSIBLE';
}
