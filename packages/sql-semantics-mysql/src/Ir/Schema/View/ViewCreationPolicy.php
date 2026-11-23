<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\View;

/**
 * Selects the outcome when the target view already exists.
 *
 * @visibility public
 * @example Selecting a replacement
 *     \SqlSemantics\Platform\MySql\Ir\Schema\View\ViewCreationPolicy::Replace->value // => 'replace'
 */
enum ViewCreationPolicy: string
{
    case RequireNew = 'require-new';
    case Replace = 'replace';
    case Preserve = 'preserve';
}
