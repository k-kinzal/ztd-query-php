<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema;

/**
 * The index-listing keyword selected by a SHOW request.
 *
 * @visibility public
 * @example Selecting SHOW KEYS
 *     \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\IndexListing::Keys->value // => 'KEYS'
 */
enum IndexListing: string
{
    case Index = 'INDEX';
    case Indexes = 'INDEXES';
    case Keys = 'KEYS';
}
