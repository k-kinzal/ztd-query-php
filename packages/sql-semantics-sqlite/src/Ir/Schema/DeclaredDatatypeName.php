<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema;

/**
 * The native declared type of an ordinary column in the complete table domain.
 *
 * @visibility public
 * @example Reading the catalog spelling
 *     \SqlSemantics\Platform\Sqlite\Ir\Schema\DeclaredDatatypeName::Integer->value // => 'INTEGER'
 */
enum DeclaredDatatypeName: string
{
    case Untyped = '';
    case Integer = 'INTEGER';
    case Text = 'TEXT';
    case Real = 'REAL';
    case Blob = 'BLOB';
}
