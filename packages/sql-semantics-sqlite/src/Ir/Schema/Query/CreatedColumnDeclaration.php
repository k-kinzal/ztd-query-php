<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Query;

/**
 * The declaration type of a column created from a result expression.
 *
 * @visibility public
 * @example Reading the native numeric affinity spelling
 *     \SqlSemantics\Platform\Sqlite\Ir\Schema\Query\CreatedColumnDeclaration::Numeric->value // => 'NUM'
 */
enum CreatedColumnDeclaration: string
{
    case Untyped = '';
    case Integer = 'INT';
    case Numeric = 'NUM';
    case Real = 'REAL';
    case Text = 'TEXT';
}
