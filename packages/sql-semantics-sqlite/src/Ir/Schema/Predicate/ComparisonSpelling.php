<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate;

/**
 * The comparison token shown in a SQLite implicit CHECK label.
 *
 * @visibility public
 * @example Retaining the alternate equality label
 *     \SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\ComparisonSpelling::DoubleEqual->value // => '=='
 */
enum ComparisonSpelling: string
{
    case Equal = '=';
    case DoubleEqual = '==';
    case NotEqual = '<>';
    case BangEqual = '!=';
    case Less = '<';
    case LessOrEqual = '<=';
    case Greater = '>';
    case GreaterOrEqual = '>=';
}
