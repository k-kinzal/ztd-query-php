<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation;

/**
 * How a named argument joins its parameter name to its value.
 *
 * Both spellings build the same `NamedArgExpr` node; the older `:=` is kept
 * for compatibility, so the model keeps the spelling written.
 * Source: https://www.postgresql.org/docs/17/sql-syntax-calling-funcs.html#SQL-SYNTAX-CALLING-FUNCS-NAMED.
 *
 * @visibility public
 * @example Spelling the standard named-argument arrow
 *     \SqlSemantics\Platform\PostgreSql\Statement\Invocation\ArgumentSpelling::Arrow->value // => '=>'
 */
enum ArgumentSpelling: string
{
    case Arrow = '=>';
    case Assignment = ':=';
}
