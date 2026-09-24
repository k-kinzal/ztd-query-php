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
    case ServerMetadata = 'server-metadata-field';
    case MaintenanceStatus = 'maintenance-status-field';
    case TableChecksum = 'table-checksum-field';
    case GeneratedPassword = 'generated-password-field';
    case XaRecovery = 'xa-recovery-field';
    case TriggerColumn = 'trigger-column';
    case Raise = 'raise';
    case Collation = 'collation';
    case ContextReference = 'context-value';
    case Variable = 'variable';
    case LocalVariable = 'local-variable';
    case UnresolvedVariable = 'unresolved-variable';
    case VariableAssignment = 'variable-assignment';
    case Column = 'column';
    case UnresolvedColumn = 'unresolved-column';
    case Wildcard = 'wildcard';
    case RowExpansion = 'row-expansion';
    case DocumentColumn = 'document-column';
    case FunctionColumn = 'function-column';
    case Literal = 'literal';
    case Parameter = 'parameter';
    case Operator = 'operator';
    case Coalesce = 'coalesce';
    case Extremum = 'extremum';
    case DateShift = 'date-shift';
    case Extract = 'extract';
    case TimestampAdd = 'timestamp-add';
    case TimestampDiff = 'timestamp-diff';
    case Position = 'position';
    case Trim = 'trim';
    case Normalization = 'normalization';
    case NormalizedPredicate = 'normalized-predicate';
    case FullTextSearch = 'full-text-search';
    case JsonPath = 'json-path';
    case JsonValue = 'json-value';
    case JsonQuery = 'json-query';
    case JsonExists = 'json-exists';
    case JsonConversion = 'json-conversion';
    case JsonConstructor = 'json-constructor';
    case XmlPredicate = 'xml-predicate';
    case XmlConversion = 'xml-conversion';
    case XmlConstructor = 'xml-constructor';
    case ArrayConstructor = 'array';
    case ArraySubquery = 'array-subquery';
    case NullIf = 'null-if';
    case Cast = 'cast';
    case Function = 'function';
    case NamedArgument = 'named-argument';
    case VariadicArgument = 'variadic-argument';
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
