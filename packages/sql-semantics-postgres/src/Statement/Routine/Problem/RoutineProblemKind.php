<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem;

/**
 * A routine definition, signature or option list that the server rejects.
 *
 * Each case carries the server's message, with `%s` for the subject.
 * Source: https://www.postgresql.org/docs/17/sql-createfunction.html,
 * https://www.postgresql.org/docs/17/sql-createprocedure.html,
 * https://www.postgresql.org/docs/17/sql-createaggregate.html,
 * https://www.postgresql.org/docs/17/sql-createoperator.html,
 * `compute_function_attributes` and `interpret_function_parameter_list` in
 * `src/backend/commands/functioncmds.c` and `oper_argtypes`, `aggr_arg` and
 * `makeOrderedSetArgs` in `src/backend/parser/gram.y` of PostgreSQL 17.
 *
 * @visibility public
 * @example Reading the message of a repeated option
 *     \SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblemKind::ConflictingOptions->value // => 'conflicting or redundant options'
 */
enum RoutineProblemKind: string
{
    case ConflictingOptions = 'conflicting or redundant options';
    case DuplicateBody = 'duplicate function body specified';
    case MissingBody = 'no function body specified';
    case MissingLanguage = 'no language specified';
    case InlineBodyLanguage = 'inline SQL function body only valid for language SQL';
    case ProcedureAttribute = 'invalid attribute in procedure definition';
    case MissingResultType = 'function result type must be specified';
    case OutputInTableFunction = 'OUT and INOUT arguments aren\'t allowed in TABLE functions';
    case VariadicNotLast = 'VARIADIC parameter must be the last input parameter';
    case DefaultOnOutput = 'only input parameters can have default values';
    case MissingDefault = 'input parameters after one with a default value must also have defaults';
    case ProcedureOutputAfterDefault = 'procedure OUT parameters cannot appear after one with a default value';
    case DuplicateParameter = 'parameter name "%s" used more than once';
    case ParallelLevel = 'parameter "parallel" must be SAFE, RESTRICTED, or UNSAFE';
    case UtilityInBody = 'a utility statement is not yet supported in unquoted SQL function body';
    case AggregateOutput = 'aggregates cannot have output arguments';
    case OrderedSetVariadic = 'an ordered-set aggregate with a VARIADIC direct argument must have one VARIADIC aggregated argument of the same data type';
    case MissingOperatorArgument = 'missing argument';
    case PostfixOperator = 'postfix operators are not supported';
    case MissingTransition = 'aggregate %s must be specified';
    case MissingRightArgument = 'operator right argument type must be specified';
    case MissingOperatorFunction = 'operator function must be specified';
}
