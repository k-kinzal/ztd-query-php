<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine;

/**
 * The return type of a loadable function: STRING, REAL, DECIMAL or INTEGER.
 *
 * Each case holds the keyword the statement is written with; INT and INTEGER
 * are one keyword of the lexer.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-function-loadable.html.
 *
 * @visibility public
 * @example Reading the keyword of a return type
 *     \SqlSemantics\Platform\MySql\Statement\Routine\LoadableResult::Integer->value // => 'INTEGER'
 */
enum LoadableResult: string
{
    case String = 'STRING';
    case Real = 'REAL';
    case Decimal = 'DECIMAL';
    case Integer = 'INTEGER';
}
