<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

/**
 * What one node of an UPSERT assignment expression is.
 *
 * A node is a value, a column of one of the two rows, or an operator written
 * over one or two others.
 */
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
