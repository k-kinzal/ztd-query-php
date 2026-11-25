<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index\Visibility;

/**
 * The physical category of a resolved secondary index.
 * @visibility public
 * @example Inspecting the native spatial index category
 *     \SqlSemantics\Platform\MySql\Ir\Schema\Index\Visibility\SecondaryIndexKind::Spatial->value // => 'spatial'
 */
enum SecondaryIndexKind: string
{
    case Ordinary = 'ordinary';
    case Unique = 'unique';
    case FullText = 'fulltext';
    case Spatial = 'spatial';
}
