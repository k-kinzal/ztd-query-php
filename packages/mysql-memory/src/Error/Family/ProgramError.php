<?php

declare(strict_types=1);

namespace MySqlMemory\Error\Family;

use MySqlMemory\Error\CatalogedError;
use MySqlMemory\Error\ErrorCode;

/**
 * A server error about stored programs: routines, triggers and events, their bodies, and the conditions they declare and signal.
 *
 * The SQLSTATE and message format of each error are those of the server error reference, which resources/errors.php holds.
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/stored-programs-views.html.
 *
 * @visibility public
 * @example Building the error of a missing routine
 *     \MySqlMemory\Error\Family\ProgramError::RoutineMissing->error('PROCEDURE', 'd.p')->getMessage() // => 'PROCEDURE d.p does not exist'
 */
enum ProgramError: int implements ErrorCode
{
    use CatalogedError;

    case RecursiveCreate = 1303;
    case RoutineExists = 1304;
    case RoutineMissing = 1305;
    case LabelMissing = 1308;
    case LabelRedefined = 1309;
    case EndLabelMismatch = 1310;
    case ReturnOutsideFunction = 1313;
    case ProgramStatement = 1314;
    case RoutineArgumentCount = 1318;
    case UndefinedCondition = 1319;
    case MissingReturn = 1320;
    case FunctionWithoutReturn = 1321;
    case UndefinedCursor = 1324;
    case CursorAlreadyOpen = 1325;
    case CursorNotOpen = 1326;
    case UndeclaredVariable = 1327;
    case WrongFetchCount = 1328;
    case NoData = 1329;
    case DuplicateParameter = 1330;
    case DuplicateVariable = 1331;
    case DuplicateCondition = 1332;
    case DuplicateCursor = 1333;
    case FunctionStatement = 1336;
    case DeclarationAfterHandler = 1337;
    case CursorAfterHandler = 1338;
    case CaseNotFound = 1339;
    case NestedProgramChange = 1357;
    case TriggerExists = 1359;
    case TriggerMissing = 1360;
    case TriggerOnView = 1361;
    case TriggerRowChange = 1362;
    case TriggerRowMissing = 1363;
    case BadSqlState = 1407;
    case DuplicateHandler = 1413;
    case NotVariableArgument = 1414;
    case ResultSetFromProgram = 1415;
    case UnsafeRoutine = 1418;
    case CommitInFunction = 1422;
    case NoRecursion = 1424;
    case TriggerInWrongSchema = 1435;
    case UsedTableInProgram = 1442;
    case RecursionLimit = 1456;
    case EventExists = 1537;
    case EventMissing = 1539;
    case IntervalNotPositive = 1542;
    case EndsBeforeStarts = 1543;
    case EventDisabledInPast = 1544;
    case SameEventName = 1551;
    case EventRecursion = 1576;
    case EventDroppedInPast = 1588;
    case WrongParametersToStoredFunction = 1584;
    case FunctionNameCollision = 1630;
    case DuplicateSignalItem = 1641;
    case SignalWarning = 1642;
    case SignalNotFound = 1643;
    case SignalException = 1644;
    case ResignalWithoutHandler = 1645;
    case SignalConditionKind = 1646;
    case ConditionItemTooLong = 1648;
    case InvalidConditionNumber = 1758;
    case StackedWithoutHandler = 3004;
    case ReferencedTriggerMissing = 3011;
    case TriggerExistsOnTable = 4099;
    case TriggerExistsElsewhere = 4100;
    case LanguageComponentUnavailable = 6001;
}
