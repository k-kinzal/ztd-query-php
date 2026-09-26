<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Model;

/**
 * The semantic operation represented by an expression.
 *
 * @example Reading semantic facts
 *     \SqlSemantics\Core\Model\ExpressionKind::Coalesce->value // => 'coalesce'
 *
 * @visibility public
 */
enum ExpressionKind: string
{
    case Column = 'column';
    case Literal = 'literal';
    case Parameter = 'parameter';
    case Operator = 'operator';
    case Coalesce = 'coalesce';
    case NullIf = 'null-if';
    case Cast = 'cast';
}
