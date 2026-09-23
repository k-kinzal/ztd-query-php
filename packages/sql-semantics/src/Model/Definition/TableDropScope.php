<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition;

/**
 * Whether table deletion resolves the normal table namespace or only temporary tables.
 * @visibility public
 * @example Selecting temporary tables
 *     \SqlSemantics\Model\Definition\TableDropScope::Temporary->value // => 'TEMPORARY'
 */
enum TableDropScope: string
{
    case Visible = '';
    case Temporary = 'TEMPORARY';
}
