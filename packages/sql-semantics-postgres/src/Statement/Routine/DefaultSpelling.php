<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Routine;

/**
 * How the default value of a routine parameter is introduced: `DEFAULT` or `=`.
 *
 * Both request the same default; the spelling is kept so that the statement is
 * written back with the same tokens.
 * Source: https://www.postgresql.org/docs/17/sql-createfunction.html.
 *
 * @visibility public
 * @example Spelling the equals sign
 *     \SqlSemantics\Platform\PostgreSql\Statement\Routine\DefaultSpelling::EqualsSign->value // => '='
 */
enum DefaultSpelling: string
{
    case Keyword = 'DEFAULT';
    case EqualsSign = '=';
}
