<?php

declare(strict_types=1);

namespace MySqlMemory\Error\Family;

use MySqlMemory\Error\CatalogedError;
use MySqlMemory\Error\ErrorCode;

/**
 * A server error about a statement as a whole: its syntax, forms the server does not support, prepared statements, table locks, XA transactions and EXPLAIN.
 *
 * The SQLSTATE and message format of each error are those of the server error reference, which resources/errors.php holds.
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/sql-statements.html.
 *
 * @visibility public
 * @example Building the error of an empty query
 *     \MySqlMemory\Error\Family\StatementError::EmptyQuery->error()->getMessage() // => 'Query was empty'
 */
enum StatementError: int implements ErrorCode
{
    use CatalogedError;

    case UnknownCommand = 1047;
    case ParseError = 1064;
    case EmptyQuery = 1065;
    case WrongFieldTerminators = 1083;
    case TableNotLockedForWrite = 1099;
    case TableNotLocked = 1100;
    case UnknownError = 1105;
    case SyntaxError = 1149;
    case LockedOrActiveTransaction = 1192;
    case WrongArguments = 1210;
    case WrongUsage = 1221;
    case DuplicateArgument = 1225;
    case CantUseOptionHere = 1234;
    case NotSupportedYet = 1235;
    case UnknownStatementHandler = 1243;
    case DeprecatedSyntax = 1287;
    case OptionPreventsStatement = 1290;
    case UnsupportedPreparedStatement = 1295;
    case FeatureDisabled = 1289;
    case ReservedSyntax = 1382;
    case XaUnknownXid = 1397;
    case XaInvalidArguments = 1398;
    case XaWrongState = 1399;
    case XaWorkOutside = 1400;
    case XaDuplicateXid = 1440;
    case DeprecatedSyntaxNoReplacement = 1681;
    case UnknownExplainFormat = 1791;
    case ExplainNotSupported = 3012;
    case LocalInfileDisabled = 3948;
    case ExplainIntoImplicitFormat = 6006;
    case ExplainIntoFormat = 6007;
    case ExplainIntoForConnection = 6009;
    case FeatureNotSupported = 6033;
    case HypergraphRequired = 6037;
    case DigestParseFailure = 3676;
    case FeatureDisabledSeeDoc = 3167;
    case HintTimeMisplaced = 3125;
    case HintConflicting = 3126;
    case HintBlockNotFound = 3127;
    case HintUnresolvedName = 3128;
    case HintNotSupported = 3515;
    case HintArgumentCount = 3614;
    case HintVariableRefused = 3637;
}
