<?php

declare(strict_types=1);

namespace MySqlMemory\Error\Message;

/**
 * SQLSTATE and message definitions owned by the StatementError cases.
 *
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 * @visibility MySqlMemory\Error
 */
final class StatementMessages
{
    /**
     * @var array<string, array{string, string}> SQLSTATE and format, indexed by the owning enum case
     */
    public const DEFINITIONS = [
        'UnknownCommand' => ['08S01', 'Unknown command'],
        'ParseError' => ['42000', 'You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near \'%s\' at line %d'],
        'EmptyQuery' => ['42000', 'Query was empty'],
        'WrongFieldTerminators' => ['42000', 'Field separator argument is not what is expected; check the manual'],
        'FixedRowsToVariable' => ['HY000', 'Can\'t load value from file with fixed size rows to variable'],
        'TableNotLockedForWrite' => ['HY000', 'Table \'%s\' was locked with a READ lock and can\'t be updated'],
        'TableNotLocked' => ['HY000', 'Table \'%s\' was not locked with LOCK TABLES'],
        'UnknownError' => ['HY000', 'Unknown error'],
        'NotAllowedCommand' => ['42000', 'The used command is not allowed with this MySQL version'],
        'SyntaxError' => ['42000', 'You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use'],
        'LockedOrActiveTransaction' => ['HY000', 'Can\'t execute the given command because you have active locked tables or an active transaction'],
        'SetConstantExpression' => ['HY000', 'You may only use constant expressions with SET'],
        'WrongArguments' => ['HY000', 'Incorrect arguments to %s'],
        'WrongUsage' => ['HY000', 'Incorrect usage of %s and %s'],
        'MisplacedInto' => ['HY000', 'Misplaced INTO clause, INTO is not allowed inside subqueries, and must be placed at end of UNION clauses.'],
        'DuplicateArgument' => ['HY000', 'Option \'%s\' used twice in statement'],
        'CantUseOptionHere' => ['42000', 'Incorrect usage/placement of \'%s\''],
        'NotSupportedYet' => ['42000', 'This version of MySQL doesn\'t yet support \'%s\''],
        'UnknownStatementHandler' => ['HY000', 'Unknown prepared statement handler (%s) given to %s'],
        'DeprecatedSyntax' => ['HY000', '\'%s\' is deprecated and will be removed in a future release. Please use %s instead'],
        'OptionPreventsStatement' => ['HY000', 'The MySQL server is running with the %s option so it cannot execute this statement'],
        'UnsupportedPreparedStatement' => ['HY000', 'This command is not supported in the prepared statement protocol yet'],
        'FeatureDisabled' => ['HY000', 'The \'%s\' feature is disabled; you need MySQL built with \'%s\' to have it working'],
        'ReservedSyntax' => ['HY000', 'The \'%s\' syntax is reserved for purposes internal to the MySQL server'],
        'QueryInterrupted' => ['70100', 'Query execution was interrupted'],
        'XaUnknownXid' => ['XAE04', 'XAER_NOTA: Unknown XID'],
        'XaInvalidArguments' => ['XAE05', 'XAER_INVAL: Invalid arguments (or unsupported command)'],
        'XaWrongState' => ['XAE07', 'XAER_RMFAIL: The command cannot be executed when global transaction is in the  %s state'],
        'XaWorkOutside' => ['XAE09', 'XAER_OUTSIDE: Some work is done outside global transaction'],
        'XaDuplicateXid' => ['XAE08', 'XAER_DUPID: The XID already exists'],
        'DeprecatedSyntaxNoReplacement' => ['HY000', '\'%s\' is deprecated and will be removed in a future release.'],
        'UnknownExplainFormat' => ['HY000', 'Unknown EXPLAIN format name: \'%s\''],
        'ExplainNotSupported' => ['HY000', 'EXPLAIN FOR CONNECTION command is supported only for SELECT/UPDATE/INSERT/DELETE/REPLACE'],
        'IllegalUserVariable' => ['42000', 'User variable name \'%s\' is illegal'],
        'LocalInfileDisabled' => ['42000', 'Loading local data is disabled; this must be enabled on both the client and server sides'],
        'ExplainAnalyzeInto' => ['0A000', 'EXPLAIN ANALYZE does not support the INTO clause.'],
        'ExplainIntoImplicitFormat' => ['0A000', 'EXPLAIN INTO does not support implicit FORMAT.'],
        'ExplainIntoFormat' => ['0A000', 'EXPLAIN INTO does not support FORMAT=%s.'],
        'ExplainIntoForConnection' => ['0A000', 'EXPLAIN FOR CONNECTION does not support the INTO clause.'],
        'ExplainAnalyzeJsonVersion' => ['0A000', 'EXPLAIN ANALYZE does not support FORMAT=JSON with explain_json_format_version=1.'],
        'DynamicParametersRequireDml' => ['HY000', 'Dynamic parameters can only be used in DML statements.'],
        'FeatureNotSupported' => ['HY000', '\'%s\' is not supported'],
        'HypergraphRequired' => ['HY000', '\'%s\' can be used only if the hypergraph optimizer is enabled.'],
        'SamplePercentageOutOfRange' => ['2202H', 'Tablesample percentage should range between 0 and 100.'],
        'SampleOnNonBaseTable' => ['HY000', 'Tablesample can be applied only on base tables.'],
        'DigestParseFailure' => ['HY000', 'Could not parse argument to digest function: "%s".'],
        'FeatureDisabledSeeDoc' => ['HY000', 'The \'%s\' feature is disabled; see the documentation for \'%s\''],
        'HintTimeMisplaced' => ['HY000', 'MAX_EXECUTION_TIME hint is supported by top-level standalone SELECT statements only'],
        'HintConflicting' => ['HY000', 'Hint %s is ignored as conflicting/duplicated'],
        'HintBlockNotFound' => ['HY000', 'Query block name %s is not found for %s hint'],
        'HintUnresolvedName' => ['HY000', 'Unresolved name %s for %s hint'],
        'HintNotSupported' => ['HY000', 'Hints aren\'t supported in %s'],
        'HintArgumentCount' => ['HY000', 'Invalid number of arguments for hint %s'],
        'HintVariableRefused' => ['HY000', 'Variable \'%s\' cannot be set using SET_VAR hint.'],
    ];
}
