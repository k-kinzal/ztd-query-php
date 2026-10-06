<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Problem;

/**
 * The rules of MySQL a grammatical stored program statement can break.
 *
 * Each case holds the message of the server error it corresponds to; `%s`
 * stands for the name the problem is about.
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 *
 * @visibility public
 * @example Reading the message pattern of a rule
 *     \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule::UndefinedCursor->value // => 'Undefined CURSOR: %s'
 */
enum ProgramRule: string
{
    case DuplicateParameter = 'Duplicate parameter: %s';
    case DuplicateVariable = 'Duplicate variable: %s';
    case DuplicateCondition = 'Duplicate condition: %s';
    case DuplicateCursor = 'Duplicate cursor: %s';
    case RedefinedLabel = 'Redefining label %s';
    case EndLabelMismatch = 'End-label %s without match';
    case LeaveWithoutLabel = 'LEAVE with no matching label: %s';
    case IterateWithoutLabel = 'ITERATE with no matching label: %s';
    case UndefinedCondition = 'Undefined CONDITION: %s';
    case UndefinedCursor = 'Undefined CURSOR: %s';
    case UndeclaredVariable = 'Undeclared variable: %s';
    case ReturnOutsideFunction = 'RETURN is only allowed in a FUNCTION';
    case MissingReturn = 'No RETURN found in FUNCTION %s';
    case DeclarationAfterCursorOrHandler = 'Variable or condition declaration after cursor or handler declaration';
    case CursorAfterHandler = 'Cursor declaration after handler declaration';
    case BadSqlState = "Bad SQLSTATE: '%s'";
    case ZeroErrorCode = "Incorrect CONDITION value: '0'";
    case DuplicateHandler = 'Duplicate handler declared in the same block';
    case SignalConditionKind = 'SIGNAL/RESIGNAL can only use a CONDITION defined with SQLSTATE';
    case DuplicateSignalItem = "Duplicate condition information item '%s'";
    case BadStatement = '%s is not allowed in stored procedures';
    case RecursiveCreate = "Can't create a %s from within another stored routine";
    case NestedAlterOrDrop = "Can't drop or alter a %s from within another stored routine";
    case EventRecursion = 'Recursion of EVENT DDL statements is forbidden when body is present';
    case ResultSet = 'Not allowed to return a result set from a %s';
    case CommitInFunction = 'Explicit or implicit commit is not allowed in stored function or trigger.';
    case FunctionStatement = '%s is not allowed in stored function or trigger';
    case OldRowUpdate = 'Updating of OLD row is not allowed in trigger';
    case AfterRowUpdate = 'Updating of NEW row is not allowed in after trigger';
    case NoNewRow = 'There is no NEW row in on DELETE trigger';
}
