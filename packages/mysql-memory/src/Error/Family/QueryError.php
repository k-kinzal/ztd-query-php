<?php

declare(strict_types=1);

namespace MySqlMemory\Error\Family;

use MySqlMemory\Error\CatalogedError;
use MySqlMemory\Error\ErrorCode;

/**
 * A server error about the names and the shape of a query: databases, tables and columns it names, grouping, subqueries, windows, common table expressions and VALUES.
 *
 * The SQLSTATE and message format of each error are those of the server error reference, which resources/errors.php holds.
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select.html.
 *
 * @visibility public
 * @example Building the error of a missing table
 *     \MySqlMemory\Error\Family\QueryError::NoSuchTable->error('shop', 'items')->getMessage() // => "Table 'shop.items' doesn't exist"
 */
enum QueryError: int implements ErrorCode
{
    use CatalogedError;

    case NoDatabase = 1046;
    case BadDatabase = 1049;
    case NonUniqueColumn = 1052;
    case BadField = 1054;
    case WrongFieldWithGroup = 1055;
    case WrongGroupField = 1056;
    case WrongValueCount = 1058;
    case NonUniqueTable = 1066;
    case UpdateTableUsed = 1093;
    case NoTablesUsed = 1096;
    case UnknownTable = 1109;
    case FieldSpecifiedTwice = 1110;
    case InvalidGroupFunctionUse = 1111;
    case WrongValueCountOnRow = 1136;
    case MixOfGroupFunctionAndFields = 1140;
    case NoSuchTable = 1146;
    case TooManyRows = 1172;
    case WrongNumberOfColumnsInSelect = 1222;
    case OperandColumns = 1241;
    case SubqueryNotOneRow = 1242;
    case IllegalReference = 1247;
    case DerivedMustHaveAlias = 1248;
    case TableNameNotAllowedHere = 1250;
    case NonUpdatableTable = 1288;
    case NonUpdatableColumn = 1348;
    case IncompleteViewKey = 1355;
    case NonGroupingFieldUsed = 1463;
    case NonInsertableTable = 1471;
    case WrongParameterCountToNativeFunction = 1582;
    case DelayedNotSupported = 1616;
    case WrongParametersToNativeFunction = 1583;
    case WrongParametersToProcedure = 1108;
    case OptionIgnored = 1618;
    case WrongVariableTypeInLimit = 1691;
    case AggregateInOrder = 3029;
    case FieldInOrderNotSelect = 3065;
    case NativeFunctionRejected = 3566;
    case UnresolvedLockedTable = 3568;
    case DuplicateLockedTable = 3569;
    case RecursiveRequiresUnion = 3573;
    case RecursiveRequiresNonrecursiveFirst = 3574;
    case RecursiveWithoutUnion = 3577;
    case WindowNotDefined = 3579;
    case WindowCircularity = 3580;
    case WindowDependentPartitioning = 3581;
    case WindowInheritedFrame = 3582;
    case WindowInheritedOrdering = 3583;
    case WindowFrameIllegal = 3586;
    case WindowRangeOrderType = 3587;
    case WindowRangeTemporalOffset = 3588;
    case WindowRangeNumericOffset = 3589;
    case WindowFrameBoundNotConstant = 3590;
    case WindowDefinedTwice = 3591;
    case WindowPositionalOrdering = 3592;
    case WindowFunctionMisplaced = 3593;
    case WindowAliasMisplaced = 3594;
    case WindowFunctionInSpecification = 3595;
    case WindowRowsInterval = 3596;
    case GroupingArgumentNotGrouped = 3602;
    case RecursionLimit = 3636;
    case TableFunctionWithoutAlias = 3667;
    case DefaultOfExpression = 3773;
    case ValuesEmptyRow = 3942;
    case ValuesDefault = 3943;
}
