<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic\Expression;

/**
 * Semantic binary operations, independent of grammar productions.
 * @example Reading semantic relationships
 *     \SqlSemantics\Semantic\Expression\BinaryOperator::Multiply->value // => '*'
 *
 * @visibility public
 */
enum BinaryOperator: string
{
    case Add = '+';
    case Subtract = '-';
    case Multiply = '*';
    case Equal = '=';
    case NotEqual = '<>';
    case Less = '<';
    case Greater = '>';
    case LessOrEqual = '<=';
    case GreaterOrEqual = '>=';
    case And = 'AND';
    case Or = 'OR';
    case Is = 'IS';
    case NullSafeEqual = '<=>';
}
