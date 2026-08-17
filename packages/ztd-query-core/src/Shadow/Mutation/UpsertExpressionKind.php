<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

enum UpsertExpressionKind
{
    case Literal;
    case Column;
    case UnaryPlus;
    case UnaryMinus;
    case Not;
    case Add;
    case Subtract;
    case Multiply;
    case Divide;
    case Modulo;
    case Equal;
    case NotEqual;
    case Less;
    case LessOrEqual;
    case Greater;
    case GreaterOrEqual;
    case And;
    case Or;
}
