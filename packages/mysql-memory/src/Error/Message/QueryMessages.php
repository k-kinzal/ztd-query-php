<?php

declare(strict_types=1);

namespace MySqlMemory\Error\Message;

/**
 * SQLSTATE and message definitions owned by the QueryError cases.
 *
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 * @visibility MySqlMemory\Error
 */
final class QueryMessages
{
    /**
     * @var array<string, array{string, string}> SQLSTATE and format, indexed by the owning enum case
     */
    public const DEFINITIONS = [
        'RewrittenQuery' => ['HY000', '%s'],
        'RecordNotFound' => ['HY000', 'Can\'t find record in \'%s\''],
        'NoDatabase' => ['3D000', 'No database selected'],
        'BadDatabase' => ['42000', 'Unknown database \'%s\''],
        'NonUniqueColumn' => ['23000', 'Column \'%s\' in %s is ambiguous'],
        'BadField' => ['42S22', 'Unknown column \'%s\' in \'%s\''],
        'WrongFieldWithGroup' => ['42000', 'Expression #%d of %s is not in GROUP BY clause and contains nonaggregated column \'%s\' which is not functionally dependent on columns in GROUP BY clause; this is incompatible with sql_mode=only_full_group_by'],
        'WrongGroupField' => ['42000', 'Can\'t group on \'%s\''],
        'WrongValueCount' => ['21S01', 'Column count doesn\'t match value count'],
        'NonUniqueTable' => ['42000', 'Not unique table/alias: \'%s\''],
        'UpdateTableUsed' => ['HY000', 'You can\'t specify target table \'%s\' for update in FROM clause'],
        'NoTablesUsed' => ['HY000', 'No tables used'],
        'UnknownTable' => ['42S02', 'Unknown table \'%s\' in %s'],
        'FieldSpecifiedTwice' => ['42000', 'Column \'%s\' specified twice'],
        'InvalidGroupFunctionUse' => ['HY000', 'Invalid use of group function'],
        'WrongValueCountOnRow' => ['21S01', 'Column count doesn\'t match value count at row %d'],
        'MixOfGroupFunctionAndFields' => ['42000', 'In aggregated query without GROUP BY, expression #%d of %s contains nonaggregated column \'%s\'; this is incompatible with sql_mode=only_full_group_by'],
        'NoSuchTable' => ['42S02', 'Table \'%s.%s\' doesn\'t exist'],
        'TooManyRows' => ['42000', 'Result consisted of more than one row'],
        'WrongNumberOfColumnsInSelect' => ['21000', 'The used SELECT statements have a different number of columns'],
        'OperandColumns' => ['21000', 'Operand should contain %d column(s)'],
        'SubqueryNotOneRow' => ['21000', 'Subquery returns more than 1 row'],
        'IllegalReference' => ['42S22', 'Reference \'%s\' not supported (%s)'],
        'DerivedMustHaveAlias' => ['42000', 'Every derived table must have its own alias'],
        'TableNameNotAllowedHere' => ['42000', 'Table \'%s\' from one of the SELECTs cannot be used in %s'],
        'NonUpdatableTable' => ['HY000', 'The target table %s of the %s is not updatable'],
        'NonUpdatableColumn' => ['HY000', 'Column \'%s\' is not updatable'],
        'IncompleteViewKey' => ['HY000', 'View being updated does not have complete key of underlying table in it'],
        'NonGroupingFieldUsed' => ['42000', 'Non-grouping field \'%s\' is used in %s clause'],
        'NonInsertableTable' => ['HY000', 'The target table %s of the %s is not insertable-into'],
        'WrongParameterCountToNativeFunction' => ['42000', 'Incorrect parameter count in the call to native function \'%s\''],
        'DelayedNotSupported' => ['HY000', 'DELAYED option not supported for table \'%s\''],
        'WrongParametersToNativeFunction' => ['42000', 'Incorrect parameters in the call to native function \'%s\''],
        'WrongParametersToProcedure' => ['HY000', 'Incorrect parameters to procedure \'%s\''],
        'OptionIgnored' => ['HY000', '<%s> option ignored'],
        'WrongVariableTypeInLimit' => ['HY000', 'A variable of a non-integer based type in LIMIT clause'],
        'AggregateInOrder' => ['HY000', 'Expression #%d of ORDER BY contains aggregate function and applies to the result of a non-aggregated query'],
        'FieldInOrderNotSelect' => ['HY000', 'Expression #%d of ORDER BY clause is not in SELECT list, references column \'%s\' which is not in SELECT list; this is incompatible with %s'],
        'AggregateInDistinctOrder' => ['HY000', 'Expression #%d of ORDER BY clause is not in SELECT list, contains aggregate function; this is incompatible with DISTINCT'],
        'NativeFunctionRejected' => ['HY000', 'Access to native function \'%s\' is rejected.'],
        'UnresolvedLockedTable' => ['HY000', 'Unresolved table name %s in locking clause.'],
        'DuplicateLockedTable' => ['HY000', 'Table %s appears in multiple locking clauses.'],
        'RecursiveRequiresUnion' => ['HY000', 'Recursive Common Table Expression \'%s\' should contain a UNION'],
        'RecursiveRequiresNonrecursiveFirst' => ['HY000', 'Recursive Common Table Expression \'%s\' should have one or more non-recursive query blocks followed by one or more recursive ones'],
        'RecursiveWithoutUnion' => ['HY000', 'In recursive query block of Recursive Common Table Expression \'%s\', the recursive table must be referenced only once, and not in any subquery'],
        'WindowNotDefined' => ['HY000', 'Window name \'%s\' is not defined.'],
        'WindowCircularity' => ['HY000', 'There is a circularity in the window dependency graph.'],
        'WindowDependentPartitioning' => ['HY000', 'A window which depends on another cannot define partitioning.'],
        'WindowInheritedFrame' => ['HY000', 'Window \'%s\' has a frame definition, so cannot be referenced by another window.'],
        'WindowInheritedOrdering' => ['HY000', 'Window \'%s\' cannot inherit \'%s\' since both contain an ORDER BY clause.'],
        'WindowFrameIllegal' => ['HY000', 'Window \'%s\': frame start or end is negative, NULL or of non-integral type'],
        'WindowRangeOrderType' => ['HY000', 'Window \'%s\' with RANGE N PRECEDING/FOLLOWING frame requires exactly one ORDER BY expression, of numeric or temporal type'],
        'WindowRangeTemporalOffset' => ['HY000', 'Window \'%s\' with RANGE frame has ORDER BY expression of datetime type. Only INTERVAL bound value allowed.'],
        'WindowRangeNumericOffset' => ['HY000', 'Window \'%s\' with RANGE frame has ORDER BY expression of numeric type, INTERVAL bound value not allowed.'],
        'WindowFrameBoundNotConstant' => ['HY000', 'Window \'%s\' has a non-constant frame bound.'],
        'WindowDefinedTwice' => ['HY000', 'Window \'%s\' is defined twice.'],
        'WindowPositionalOrdering' => ['HY000', 'Window \'%s\': ORDER BY or PARTITION BY uses legacy position indication which is not supported, use expression.'],
        'WindowFunctionMisplaced' => ['HY000', 'You cannot use the window function \'%s\' in this context.\''],
        'WindowAliasMisplaced' => ['HY000', 'You cannot use the alias \'%s\' of an expression containing a window function in this context.\''],
        'WindowFunctionInSpecification' => ['HY000', 'You cannot nest a window function in the specification of window \'%s\'.'],
        'WindowRowsInterval' => ['HY000', 'Window \'%s\': INTERVAL can only be used with RANGE frames.'],
        'GroupingArgumentNotGrouped' => ['HY000', 'Argument #%s of GROUPING function is not in GROUP BY'],
        'RecursionLimit' => ['HY000', 'Recursive query aborted after %d iterations. Try increasing @@cte_max_recursion_depth to a larger value.'],
        'TableFunctionWithoutAlias' => ['42000', 'Every table function must have an alias'],
        'DefaultOfExpression' => ['HY000', 'DEFAULT function cannot be used with default value expressions'],
        'ValuesEmptyRow' => ['HY000', 'Each row of a VALUES clause must have at least one column, unless when used as source in an INSERT statement.'],
        'ValuesDefault' => ['HY000', 'A VALUES clause cannot use DEFAULT values, unless used as a source in an INSERT statement.'],
    ];
}
