<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Handler;

/**
 * How HANDLER ... READ index compares the index key with the given values to find the first row.
 *
 * Each case holds the operator it is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/handler.html.
 *
 * @visibility public
 * @example Reading the operator of a case
 *     \SqlSemantics\Platform\MySql\Statement\Dml\Handler\KeyComparison::GreaterOrEqual->value // => '>='
 */
enum KeyComparison: string
{
    case Equal = '=';
    case GreaterOrEqual = '>=';
    case LessOrEqual = '<=';
    case Greater = '>';
    case Less = '<';
}
