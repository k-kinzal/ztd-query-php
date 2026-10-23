<?php

declare(strict_types=1);

namespace MySqlMemory\Error\Message;

/**
 * SQLSTATE and message definitions owned by the ProgramError cases.
 *
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 * @visibility MySqlMemory\Error
 */
final class ProgramMessages
{
    /**
     * @var array<string, array{string, string}> SQLSTATE and format, indexed by the owning enum case
     */
    public const DEFINITIONS = [
        'RecursiveCreate' => ['2F003', 'Can\'t create a %s from within another stored routine'],
        'RoutineExists' => ['42000', '%s %s already exists'],
        'RoutineMissing' => ['42000', '%s %s does not exist'],
        'LabelMissing' => ['42000', '%s with no matching label: %s'],
        'LabelRedefined' => ['42000', 'Redefining label %s'],
        'EndLabelMismatch' => ['42000', 'End-label %s without match'],
        'ReturnOutsideFunction' => ['42000', 'RETURN is only allowed in a FUNCTION'],
        'ProgramStatement' => ['0A000', '%s is not allowed in stored procedures'],
        'RoutineArgumentCount' => ['42000', 'Incorrect number of arguments for %s %s; expected %s, got %s'],
        'UndefinedCondition' => ['42000', 'Undefined CONDITION: %s'],
        'MissingReturn' => ['42000', 'No RETURN found in FUNCTION %s'],
        'FunctionWithoutReturn' => ['2F005', 'FUNCTION %s ended without RETURN'],
        'UndefinedCursor' => ['42000', 'Undefined CURSOR: %s'],
        'CursorAlreadyOpen' => ['24000', 'Cursor is already open'],
        'CursorNotOpen' => ['24000', 'Cursor is not open'],
        'UndeclaredVariable' => ['42000', 'Undeclared variable: %s'],
        'WrongFetchCount' => ['HY000', 'Incorrect number of FETCH variables'],
        'NoData' => ['02000', 'No data - zero rows fetched, selected, or processed'],
        'DuplicateParameter' => ['42000', 'Duplicate parameter: %s'],
        'DuplicateVariable' => ['42000', 'Duplicate variable: %s'],
        'DuplicateCondition' => ['42000', 'Duplicate condition: %s'],
        'DuplicateCursor' => ['42000', 'Duplicate cursor: %s'],
        'FunctionStatement' => ['0A000', '%s is not allowed in stored function or trigger'],
        'DeclarationAfterHandler' => ['42000', 'Variable or condition declaration after cursor or handler declaration'],
        'CursorAfterHandler' => ['42000', 'Cursor declaration after handler declaration'],
        'CaseNotFound' => ['20000', 'Case not found for CASE statement'],
        'NestedProgramChange' => ['HY000', 'Can\'t drop or alter a %s from within another stored routine'],
        'TriggerExists' => ['HY000', 'Trigger already exists'],
        'TriggerMissing' => ['HY000', 'Trigger does not exist'],
        'TriggerOnView' => ['HY000', 'Trigger\'s \'%s\' is a view, temporary table or sequence'],
        'TriggerRowChange' => ['HY000', 'Updating of %s row is not allowed in %strigger'],
        'TriggerRowMissing' => ['HY000', 'There is no %s row in %s trigger'],
        'BadSqlState' => ['42000', 'Bad SQLSTATE: \'%s\''],
        'DuplicateHandler' => ['42000', 'Duplicate handler declared in the same block'],
        'NotVariableArgument' => ['42000', 'OUT or INOUT argument %d for routine %s is not a variable or NEW pseudo-variable in BEFORE trigger'],
        'ResultSetFromProgram' => ['0A000', 'Not allowed to return a result set from a %s'],
        'UnsafeRoutine' => ['HY000', 'This function has none of DETERMINISTIC, NO SQL, or READS SQL DATA in its declaration and binary logging is enabled (you *might* want to use the less safe log_bin_trust_function_creators variable)'],
        'CommitInFunction' => ['HY000', 'Explicit or implicit commit is not allowed in stored function or trigger.'],
        'NoRecursion' => ['HY000', 'Recursive stored functions and triggers are not allowed.'],
        'TriggerInWrongSchema' => ['HY000', 'Trigger in wrong schema'],
        'UsedTableInProgram' => ['HY000', 'Can\'t update table \'%s\' in stored function/trigger because it is already used by statement which invoked this stored function/trigger.'],
        'AutocommitInFunction' => ['HY000', 'Not allowed to set autocommit from a stored function or trigger'],
        'VariableShadow' => ['42000', 'Variable \'%s\' must be quoted with `...`, or renamed'],
        'RecursionLimit' => ['HY000', 'Recursive limit %d (as set by the max_sp_recursion_depth variable) was exceeded for routine %s'],
        'IncorrectName' => ['42000', 'Incorrect routine name \'%s\''],
        'EventExists' => ['HY000', 'Event \'%s\' already exists'],
        'EventMissing' => ['HY000', 'Unknown event \'%s\''],
        'IntervalNotPositive' => ['HY000', 'INTERVAL is either not positive or too big'],
        'EndsBeforeStarts' => ['HY000', 'ENDS is either invalid or before STARTS'],
        'EventDisabledInPast' => ['HY000', 'Event execution time is in the past. Event has been disabled'],
        'SameEventName' => ['HY000', 'Same old and new event name'],
        'EventRecursion' => ['HY000', 'Recursion of EVENT DDL statements is forbidden when body is present'],
        'EventDroppedInPast' => ['HY000', 'Event execution time is in the past and ON COMPLETION NOT PRESERVE is set. The event was dropped immediately after creation.'],
        'EventUnchangedInPast' => ['HY000', 'Event execution time is in the past and ON COMPLETION NOT PRESERVE is set. The event was not changed. Specify a time in the future.'],
        'WrongParametersToStoredFunction' => ['42000', 'Incorrect parameters in the call to stored function %s'],
        'FunctionNameCollision' => ['42000', 'FUNCTION %s does not exist. Check the \'Function Name Parsing and Resolution\' section in the Reference Manual'],
        'DuplicateSignalItem' => ['42000', 'Duplicate condition information item \'%s\''],
        'SignalWarning' => ['01000', 'Unhandled user-defined warning condition'],
        'SignalNotFound' => ['02000', 'Unhandled user-defined not found condition'],
        'SignalException' => ['HY000', 'Unhandled user-defined exception condition'],
        'ResignalWithoutHandler' => ['0K000', 'RESIGNAL when handler not active'],
        'SignalConditionKind' => ['HY000', 'SIGNAL/RESIGNAL can only use a CONDITION defined with SQLSTATE'],
        'ConditionItemTooLong' => ['HY000', 'Data too long for condition item \'%s\''],
        'InvalidConditionNumber' => ['35000', 'Invalid condition number'],
        'StackedWithoutHandler' => ['0Z002', 'GET STACKED DIAGNOSTICS when handler not active'],
        'ReferencedTriggerMissing' => ['HY000', 'Referenced trigger \'%s\' for the given action time and event type does not exist.'],
        'TriggerExistsOnTable' => ['HY000', 'Trigger \'%s\' already exists on the table \'%s\'.\'%s\'.'],
        'TriggerExistsElsewhere' => ['HY000', 'Trigger \'%s\'.\'%s\' already exists on a different table. The \'IF NOT EXISTS\' clause is only supported for triggers associated with the same table.'],
        'LanguageComponentUnavailable' => ['HY000', 'Language component: Not available.'],
    ];
}
