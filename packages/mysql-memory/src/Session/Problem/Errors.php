<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem;

use MySqlMemory\Error\ErrorNumbers;
use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\RepeatedTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\TableExists;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\UnknownAlterChoice;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\UnknownColumn;
use SqlSemantics\Platform\MySql\Statement\Alter\TruncateTable;
use SqlSemantics\Platform\MySql\Statement\Call\Clock;
use SqlSemantics\Platform\MySql\Statement\Call\ClockCall;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\NamedArgument;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\ReservedFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\UnsupportedWindowing;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WrongArgumentCount;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\DuplicateColumn as DuplicateWrittenColumn;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\GeneratedColumnWrite;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\UnknownDeleteTable;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\ValueCountMismatch;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteMisuse;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteRule;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\CollationMismatch;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\IllegalCollationMix;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\TooBigPrecision;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation;
use SqlSemantics\Platform\MySql\Statement\Name\AmbiguousAlias;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountedList;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountMismatch;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\GroupingRule;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\NonGroupedColumn;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\OrdinalOutOfRange;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\UndeclaredVariable;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\UnknownPartition;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\UnknownQualifier;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\UnpartitionedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\BucketCountOutOfRange;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\DuplicateColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\IncorrectColumnName;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\MultiplePrimaryKeys;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\NoColumns;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\NullablePrimaryKey;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\UnknownKeyColumn;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\VariableMisuse;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Reference\Column\AmbiguousColumn;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Table\MissingTable;

/**
 * Answers the error the server reports for a problem SQL Semantics found in a statement.
 *
 * Each family of diagnostics, the names a statement reads, the rules of a query, the calls of
 * native functions, the definitions of tables, the writes, the expressions and the server
 * statements, has its own dispatcher; a diagnostic no family knows is ER_UNKNOWN_ERROR with its
 * message.
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 *
 * @visibility MySqlMemory
 */
final class Errors
{
    /**
     * Answers the server error of a diagnostic, for a name read in a clause of a statement.
     */
    public function error(Diagnostic $diagnostic, Session $session, string $clause = 'field list', ?Node $statement = null): SqlError
    {
        $database = $session->variables->database;

        return $this->unopened($diagnostic, $session, $statement)
            ?? $this->names($diagnostic, $database, $clause, $statement)
            ?? $this->query($diagnostic, $clause)
            ?? $this->call($diagnostic)
            ?? $this->definition($diagnostic, $database)
            ?? $this->manipulation($diagnostic, $statement)
            ?? $this->expression($diagnostic)
            ?? $this->server($diagnostic, $session, $statement)
            ?? new SqlError(StatementError::UnknownError, $diagnostic->message());
    }

    /**
     * Answers the server error of a located problem: an error made where it was located, a call of a function the server does not find, a clock call whose precision is above 6, or a diagnostic of a name read in a clause.
     *
     * A problem of a column of MATCH is followed by the error of its AGAINST (ER_WRONG_ARGUMENTS).
     */
    public function located(Diagnostic|FunctionCall|ClockCall|SqlError $problem, string $clause, bool $matched, Session $session, Node $statement): SqlError
    {
        $error = match (true) {
            $problem instanceof SqlError => $problem,
            $problem instanceof FunctionCall => $this->routine($problem, $session),
            $problem instanceof ClockCall => $this->precision($problem),
            default => $this->error($problem, $session, $clause, $statement),
        };

        return $matched ? new SqlError($error->error, $error->getMessage(), null, [[StatementError::WrongArguments->value, StatementError::WrongArguments->message('AGAINST')]]) : $error;
    }

    /**
     * Answers the server error of a table named in a database it cannot open, or null for another diagnostic.
     *
     * A database that does not exist is ER_BAD_DB_ERROR, and a table INFORMATION_SCHEMA lacks is
     * ER_UNKNOWN_TABLE naming the table in upper case (verified on live 8.0, 8.4 and 9.1 servers).
     * MySQL 5.6 and 5.7 report the table as missing instead (unknown()), and so does TRUNCATE
     * TABLE in every release.
     */
    public function unopened(Diagnostic $diagnostic, Session $session, ?Node $statement = null): ?SqlError
    {
        $schema = $diagnostic instanceof MissingTable && !$statement instanceof TruncateTable ? $diagnostic->name->schema : null;
        if ($schema === null) {
            return null;
        }
        if (strcasecmp($schema->value, 'information_schema') === 0) {
            return QueryError::UnknownTable->error(strtoupper($diagnostic->name->name->value), 'information_schema');
        }

        return $session->instance->dictionary->schema($schema->value) === null ? self::unknown($schema->value, $diagnostic->name->name->value, $session->settings()->release()) : null;
    }

    /**
     * Answers the error of a table named in a database that does not exist: ER_BAD_DB_ERROR, or in MySQL 5.6 and 5.7 ER_NO_SUCH_TABLE (verified on live 5.6.51 and 5.7.44 servers).
     */
    public static function unknown(string $schema, string $table, \SqlSemantics\Contract\GrammarRelease $release): SqlError
    {
        return $release === \SqlSemantics\Contract\GrammarRelease::MySql5651 || $release === \SqlSemantics\Contract\GrammarRelease::MySql5744 ? QueryError::NoSuchTable->error($schema, $table) : QueryError::BadDatabase->error($schema);
    }

    /**
     * Answers the server error of a name that does not resolve or resolves twice, or null for another diagnostic.
     *
     * A table without a database, when none is selected, is ER_NO_DB_ERROR. A column a USING list
     * names is reported in the FROM clause.
     */
    public function names(Diagnostic $diagnostic, string $database, string $clause, ?Node $statement): ?SqlError
    {
        return match (true) {
            $diagnostic instanceof MissingTable => $diagnostic->name->schema === null && $database === '' ? QueryError::NoDatabase->error() : QueryError::NoSuchTable->error($diagnostic->name->schema->value ?? $database, $diagnostic->name->name->value),
            $diagnostic instanceof MissingColumn => QueryError::BadField->error(($diagnostic->qualifier?->schema === null ? '' : $diagnostic->qualifier->schema->value . '.') . ($diagnostic->qualifier === null ? '' : $diagnostic->qualifier->name->value . '.') . $diagnostic->name->value, $this->joining($diagnostic, $statement) ? 'from clause' : $clause),
            $diagnostic instanceof AmbiguousColumn => QueryError::NonUniqueColumn->error($diagnostic->name->value, $clause),
            $diagnostic instanceof AmbiguousAlias => QueryError::NonUniqueColumn->error($diagnostic->name->value, $clause),
            default => null,
        };
    }

    /**
     * Answers the server error of a rule of a query block a statement breaks, or null for another diagnostic.
     *
     * A position outside the select list is reported in the clause that names it: GROUP BY, or else ORDER BY.
     */
    public function query(Diagnostic $diagnostic, string $clause): ?SqlError
    {
        return match (true) {
            $diagnostic instanceof OrdinalOutOfRange => QueryError::BadField->error((string) $diagnostic->position, $clause === 'group statement' ? $clause : 'order clause'),
            $diagnostic instanceof NonUniqueTable => QueryError::NonUniqueTable->error($diagnostic->alias->value),
            $diagnostic instanceof UndeclaredVariable => ProgramError::UndeclaredVariable->error($diagnostic->name->value),
            $diagnostic instanceof Misuse => $this->misuse($diagnostic),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\CacheOptionConflict => $diagnostic->repeated() ? StatementError::DuplicateArgument->error($diagnostic->first->value) : StatementError::WrongUsage->error($diagnostic->first->value, $diagnostic->second->value),
            $diagnostic instanceof NonGroupedColumn => new SqlError(match ($diagnostic->rule) {
                GroupingRule::NotDetermined => QueryError::WrongFieldWithGroup,
                GroupingRule::WithoutGroupBy => QueryError::MixOfGroupFunctionAndFields,
                GroupingRule::NotSelected => QueryError::FieldInOrderNotSelect,
            }, $diagnostic->message()),
            $diagnostic instanceof UnknownQualifier => SchemaError::BadTable->error(($diagnostic->table->schema === null ? '' : $diagnostic->table->schema->value . '.') . $diagnostic->table->name->value),
            $diagnostic instanceof CountMismatch => $this->counted($diagnostic),
            $diagnostic instanceof UnpartitionedTable => SchemaError::PartitionClauseOnNonpartitioned->error(),
            $diagnostic instanceof UnknownPartition => SchemaError::UnknownPartition->error($diagnostic->partition, $diagnostic->table),
            default => null,
        };
    }

    /**
     * Answers the server error of a list whose length differs from the one it must have: the
     * columns of set operands and INTO variables, the values of a row, or the columns of a derived table.
     */
    public function counted(CountMismatch $diagnostic): SqlError
    {
        return match ($diagnostic->list) {
            CountedList::SetOperands, CountedList::IntoVariables => QueryError::WrongNumberOfColumnsInSelect->error(),
            CountedList::ValueRows => QueryError::WrongValueCountOnRow->error($diagnostic->row),
            CountedList::DerivedColumns => SchemaError::ViewWrongList->error(),
        };
    }

    /**
     * Answers the server error of a wrong call of a native function, or null for another diagnostic.
     */
    public function call(Diagnostic $diagnostic): ?SqlError
    {
        return match (true) {
            $diagnostic instanceof WrongArgumentCount => QueryError::WrongParameterCountToNativeFunction->error($diagnostic->function->value),
            $diagnostic instanceof NamedArgument => QueryError::WrongParametersToNativeFunction->error(strtolower($diagnostic->function->value)),
            $diagnostic instanceof ReservedFunction => QueryError::NativeFunctionRejected->error($diagnostic->function->value),
            $diagnostic instanceof UnsupportedWindowing => StatementError::NotSupportedYet->error($diagnostic->limit->value),
            default => null,
        };
    }

    /**
     * Answers the server error of a problem of a table or view definition, or null for another diagnostic.
     */
    public function definition(Diagnostic $diagnostic, string $database): ?SqlError
    {
        return match (true) {
            $diagnostic instanceof RepeatedTable => QueryError::NonUniqueTable->error($diagnostic->name->name->value),
            $diagnostic instanceof MultiplePrimaryKeys => SchemaError::MultiplePrimaryKey->error(),
            $diagnostic instanceof NoColumns => SchemaError::TableMustHaveColumns->error(),
            $diagnostic instanceof DuplicateColumn => SchemaError::DuplicateFieldName->error($diagnostic->column->value),
            $diagnostic instanceof IncorrectColumnName => SchemaError::WrongColumnName->error($diagnostic->column->value),
            $diagnostic instanceof NullablePrimaryKey => SchemaError::PrimaryCantHaveNull->error(),
            $diagnostic instanceof UnknownKeyColumn => SchemaError::KeyColumnMissing->error($diagnostic->column->value),
            $diagnostic instanceof UnknownColumn => SchemaError::FieldNotFoundInPartitionFunction->error(),
            $diagnostic instanceof TableExists => SchemaError::TableExists->error($diagnostic->name->name->value),
            $diagnostic instanceof UnknownAlterChoice => ($diagnostic->lock ? SchemaError::UnknownAlterLock : SchemaError::UnknownAlterAlgorithm)->error($diagnostic->name->value),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\WrongRelationKind => (new \MySqlMemory\Error\ProgramErrors())->relation($diagnostic, $database),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\ViewColumnCount => SchemaError::ViewWrongList->error(),
            default => null,
        };
    }

    /**
     * Answers the server error of a problem of a data manipulation statement, or null for another diagnostic.
     */
    public function manipulation(Diagnostic $diagnostic, ?Node $statement): ?SqlError
    {
        return match (true) {
            $diagnostic instanceof ValueCountMismatch => $diagnostic->row === null ? QueryError::WrongValueCount->error() : QueryError::WrongValueCountOnRow->error($diagnostic->row),
            $diagnostic instanceof DuplicateWrittenColumn => QueryError::FieldSpecifiedTwice->error($diagnostic->column->value),
            $diagnostic instanceof GeneratedColumnWrite => DataError::GeneratedColumnValue->error($diagnostic->column->value, $diagnostic->table->value),
            $diagnostic instanceof WriteMisuse => $this->write($diagnostic, $statement),
            $diagnostic instanceof UnknownDeleteTable => QueryError::UnknownTable->error($diagnostic->table->name->value, 'MULTI DELETE'),
            default => null,
        };
    }

    /**
     * Answers the server error of a problem of an expression, or null for another diagnostic.
     *
     * An unknown collation is named by its first 64 characters.
     */
    public function expression(Diagnostic $diagnostic): ?SqlError
    {
        return match (true) {
            $diagnostic instanceof OperandColumns => QueryError::OperandColumns->error($diagnostic->expected),
            $diagnostic instanceof NotSupportedYet => StatementError::NotSupportedYet->error($diagnostic->feature),
            $diagnostic instanceof IllegalCollationMix => new SqlError(match (count($diagnostic->operands)) {
                2 => DataError::CantAggregateTwoCollations,
                3 => DataError::CantAggregateThreeCollations,
                default => DataError::CantAggregateCollations,
            }, $diagnostic->message()),
            $diagnostic instanceof UnknownCollation => SchemaError::UnknownCollation->error(strlen(mb_strcut($diagnostic->name, 0, 64, 'UTF-8')) < min(64, strlen($diagnostic->name)) ? mb_strcut($diagnostic->name, 0, 64, 'UTF-8') . '?' : substr($diagnostic->name, 0, 64)),
            $diagnostic instanceof TooBigPrecision => SchemaError::TooBigPrecision->error($diagnostic->precision, $diagnostic->function, 6),
            $diagnostic instanceof UnknownCharset => SchemaError::UnknownCharacterSet->error($diagnostic->name),
            $diagnostic instanceof CollationMismatch => SchemaError::CollationCharsetMismatch->error($diagnostic->collation, $diagnostic->charset),
            default => null,
        };
    }

    /**
     * Answers the server error of a problem of a server, variable, program or utility statement, or null for another diagnostic.
     *
     * SHOW PROCEDURE CODE and SHOW FUNCTION CODE need a server built with debugging.
     */
    public function server(Diagnostic $diagnostic, Session $session, ?Node $statement): ?SqlError
    {
        return match (true) {
            $diagnostic instanceof BucketCountOutOfRange => DataError::DataOutOfRange->error('Number of buckets', 'ANALYZE TABLE'),
            $diagnostic instanceof UnknownSystemVariable => AdministrationError::UnknownSystemVariable->error($diagnostic->name),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnstructuredVariable => AdministrationError::VariableIsNotStruct->error($diagnostic->name),
            $diagnostic instanceof VariableMisuse => new SqlError(ErrorNumbers::from($diagnostic->rule->code()), $diagnostic->message()),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem => (new \MySqlMemory\Error\ProgramErrors())->error($diagnostic),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityMisuse && $diagnostic->rule === \SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityRule::DebugOnly => StatementError::FeatureDisabled->error('SHOW PROCEDURE|FUNCTION CODE', '--with-debug'),
            \MySqlMemory\Command\Admin\Refusals::handles($diagnostic) => (new \MySqlMemory\Command\Admin\Refusals())->error($diagnostic, $statement, $session->text),
            default => null,
        };
    }

    /**
     * Answers the server error of a rule a data manipulation statement breaks.
     */
    public function write(WriteMisuse $misuse, ?Node $statement): SqlError
    {
        return match ($misuse->rule) {
            WriteRule::DefaultOutsideInsert => QueryError::ValuesDefault->error(),
            WriteRule::OrderedMultipleUpdate => StatementError::WrongUsage->error('UPDATE', 'ORDER BY'),
            WriteRule::LimitedMultipleUpdate => StatementError::WrongUsage->error('UPDATE', 'LIMIT'),
            WriteRule::WildcardColumn => QueryError::BadField->error('*', 'field list'),
            WriteRule::CommonTableTarget, WriteRule::NonUpdatableTarget => QueryError::NonUpdatableTable->error($misuse->table->value ?? '', $statement instanceof Update ? 'UPDATE' : 'DELETE'),
            WriteRule::NonUpdatableColumn => QueryError::NonUpdatableColumn->error($misuse->table->value ?? ''),
        };
    }

    /**
     * Tells whether a missing column is a column a USING list of the statement names, which the server reports in the FROM clause (verified on a live 8.4 server).
     */
    public function joining(MissingColumn $diagnostic, ?Node $statement): bool
    {
        foreach ($statement === null ? [] : (new Walker())->find($statement, JoinedTable::class) as $join) {
            if (in_array($diagnostic->name, $join->using, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Answers the server error of a broken rule of a query.
     */
    public function misuse(Misuse $misuse): SqlError
    {
        $name = self::named($misuse);

        return match ($misuse->rule) {
            MisuseRule::StarWithoutTables => QueryError::NoTablesUsed->error(),
            MisuseRule::DerivedWithoutAlias => QueryError::DerivedMustHaveAlias->error(),
            MisuseRule::TableFunctionWithoutAlias => QueryError::TableFunctionWithoutAlias->error(),
            MisuseRule::DuplicateAlias, MisuseRule::DuplicateCommonTable => QueryError::NonUniqueTable->error($name),
            MisuseRule::RecursiveWithoutUnion => QueryError::RecursiveRequiresUnion->error($name),
            MisuseRule::RecursiveWithoutAnchor => QueryError::RecursiveRequiresNonrecursiveFirst->error($name),
            MisuseRule::DuplicateColumn => SchemaError::DuplicateFieldName->error($name),
            MisuseRule::DuplicateWindow => QueryError::WindowDefinedTwice->error($name),
            MisuseRule::UnknownWindow => QueryError::WindowNotDefined->error($name),
            MisuseRule::UnknownLockedTable => QueryError::UnresolvedLockedTable->error(self::quoted($misuse)),
            MisuseRule::RepeatedLockedTable => QueryError::DuplicateLockedTable->error($misuse->name instanceof QualifiedName ? self::quoted($misuse) : $name),
            MisuseRule::AmbiguousJoinColumn => QueryError::NonUniqueColumn->error($name, 'from clause'),
            MisuseRule::EmptyValuesRow => QueryError::ValuesEmptyRow->error(),
        };
    }

    /**
     * Answers the name a broken rule of a query is about, without its database: empty when it names nothing.
     */
    public static function named(Misuse $misuse): string
    {
        return match (true) {
            $misuse->name instanceof Name => $misuse->name->value,
            $misuse->name instanceof QualifiedName => $misuse->name->name->value,
            default => '',
        };
    }

    /**
     * Answers the name of the table a broken rule of a query is about in backquotes, after its database in backquotes when it names one.
     */
    public static function quoted(Misuse $misuse): string
    {
        $name = self::named($misuse);

        return $misuse->name instanceof QualifiedName && $misuse->name->schema !== null ? '`' . $misuse->name->schema->value . '`.`' . $name . '`' : '`' . $name . '`';
    }

    /**
     * Answers the error of a clock call whose precision is above 6, named as the server names the function.
     *
     * Source: https://dev.mysql.com/doc/refman/8.4/en/fractional-seconds.html.
     */
    public function precision(ClockCall $call): SqlError
    {
        $name = match ($call->clock) {
            Clock::Now => 'now',
            Clock::CurrentTime => 'curtime',
            Clock::SystemDate => 'sysdate',
            Clock::UtcTime => 'utc_time',
            Clock::UtcTimestamp => 'utc_timestamp',
            Clock::CurrentDate => 'curdate',
            Clock::UtcDate => 'utc_date',
        };

        return SchemaError::TooBigPrecision->error($call->decimals(), $name, 6);
    }

    /**
     * Answers the error of a call of a stored function that does not exist, in the database the call names or the current one.
     */
    public function routine(FunctionCall $call, Session $session): SqlError
    {
        $database = $session->variables->database;
        if ($call->schema === null && $database === '') {
            return QueryError::NoDatabase->error();
        }

        $function = $session->instance->dictionary->schema($call->schema->value ?? $database)->functions[strtolower($call->name->value)] ?? null;
        if ($function !== null) {
            $count = count($function->statement->parameters->parameters);

            return $count === count($call->arguments) ? StatementError::NotSupportedYet->error('calls of stored functions') : ProgramError::RoutineArgumentCount->error('FUNCTION', $function->schema . '.' . $function->name, $count, count($call->arguments));
        }

        return ProgramError::RoutineMissing->error('FUNCTION', ($call->schema->value ?? $database) . '.' . $call->name->value);
    }
}
