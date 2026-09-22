<?php

declare(strict_types=1);

namespace SqlSemantics\Model;

/**
 * The semantic operation represented by an expression.
 *
 * @example Reading semantic facts
 *     \SqlSemantics\Model\ExpressionKind::Coalesce->value // => 'coalesce'
 *
 * @visibility public
 */
enum ExpressionKind: string
{
    case TriggerColumn = 'trigger-column';
    case Raise = 'raise';
    case Collation = 'collation';
    case ContextReference = 'context-value';
    case Variable = 'variable';
    case UnresolvedVariable = 'unresolved-variable';
    case VariableAssignment = 'variable-assignment';
    case Column = 'column';
    case UnresolvedColumn = 'unresolved-column';
    case Wildcard = 'wildcard';
    case DocumentColumn = 'document-column';
    case Literal = 'literal';
    case Parameter = 'parameter';
    case Operator = 'operator';
    case Coalesce = 'coalesce';
    case Extremum = 'extremum';
    case NullIf = 'null-if';
    case Cast = 'cast';
    case Function = 'function';
    case Aggregate = 'aggregate';
    case Window = 'window';
    case CaseExpression = 'case';
    case Subquery = 'subquery';
    case RowSubquery = 'row-subquery';
    case Row = 'row';
    case Subscript = 'subscript';
    case Field = 'field';
    case CurrentRow = 'current-row';
    case ConfigurationValue = 'configuration-value';
}
