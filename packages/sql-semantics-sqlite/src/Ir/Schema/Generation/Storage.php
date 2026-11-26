<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Generation;

/**
 * The native storage role of a generated column.
 *
 * @visibility public
 * @example Reading the stored mode keyword
 *     \SqlSemantics\Platform\Sqlite\Ir\Schema\Generation\Storage::Stored->value // => 'STORED'
 */
enum Storage: string
{
    case Virtual = 'VIRTUAL';
    case Stored = 'STORED';
}
