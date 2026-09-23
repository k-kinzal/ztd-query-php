<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Maintenance\IndexCache;

/**
 * The server key cache created at startup.
 * @visibility public
 * @example Choosing the built-in selection
 *     \SqlSemantics\Model\Maintenance\IndexCache\DefaultCache::Instance->value // => 'DEFAULT'
 */
enum DefaultCache: string
{
    case Instance = 'DEFAULT';
}
