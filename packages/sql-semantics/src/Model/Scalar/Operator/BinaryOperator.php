<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Operator;

/**
 * Closed BinaryOperator alternatives.
 * @visibility public
 */
enum BinaryOperator: string
{
    case Add = '+';
    case Subtract = '-';
    case Multiply = '*';
    case Divide = '/';
    case Remainder = '%';
    case IntegerDivide = 'DIV';
    case Modulo = 'MOD';
    case Power = '^';
    case BitAnd = '&';
    case BitOr = '|';
    case BitXor = 'XOR';
    case ShiftLeft = '<<';
    case ShiftRight = '>>';
    case Concat = '||';
    case Equal = '=';
    case NotEqual = '<>';
    case NotEqualBang = '!=';
    case Less = '<';
    case Greater = '>';
    case LessEqual = '<=';
    case GreaterEqual = '>=';
    case And = 'AND';
    case Or = 'OR';
    case Is = 'IS';
    case IsNot = 'IS NOT';
    case NullSafeEqual = '<=>';
    case Distinct = 'IS DISTINCT FROM';
    case NotDistinct = 'IS NOT DISTINCT FROM';
    case JsonExtract = '->';
    case JsonText = '->>';
}
