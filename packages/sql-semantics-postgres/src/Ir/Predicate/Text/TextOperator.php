<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Predicate\Text;

/**
 * A stock comparison operator for two PostgreSQL text values.
 *
 * @visibility public
 * @example Reading the builtin inequality operation
 *     \SqlSemantics\Platform\PostgreSql\Ir\Predicate\Text\TextOperator::NotEqual->value // => '<>'
 */
enum TextOperator: string
{
    case Equal = '=';
    case NotEqual = '<>';
    case Less = '<';
    case LessOrEqual = '<=';
    case Greater = '>';
    case GreaterOrEqual = '>=';
}
