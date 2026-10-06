<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Operator;

/**
 * Whether an operator family member is an operator or a support function.
 *
 * Source: https://www.postgresql.org/docs/17/sql-alteropfamily.html.
 *
 * @visibility public
 * @example Spelling the function kind
 *     \SqlSemantics\Platform\PostgreSql\Statement\Operator\MemberKind::Function->value // => 'FUNCTION'
 */
enum MemberKind: string
{
    case Operator = 'OPERATOR';
    case Function = 'FUNCTION';
}
