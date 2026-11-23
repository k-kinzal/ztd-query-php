<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\From;

/**
 * The native kind of a consumed field supplied by an ordinary table.
 *
 * @visibility public
 * @example Identifying a text column
 *     \SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind::Text->value // => 'text'
 */
enum ColumnKind: string
{
    case Int4 = 'int4';
    case Text = 'text';
    case Boolean = 'bool';
    case Int8 = 'int8';
}
