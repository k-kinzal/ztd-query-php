<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Schema\Definition;

/**
 * A key's declared ordering; descending column primary keys have distinct SQLite rowid behavior.
 * @visibility public
 * @example Requesting descending key order
 *     \SqlSemantics\Statement\Schema\Definition\KeyDirection::Descending->value // => 'DESC'
 */
enum KeyDirection: string
{
    case Implicit = '';
    case Ascending = 'ASC';
    case Descending = 'DESC';
}
