<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem;

use LogicException;
use MySqlMemory\Error\ErrorNumbers;
use MySqlMemory\Error\Family\AccountError;
use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Error\TypeErrors;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\RepeatedTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\TableExists;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\UnknownAlterChoice;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\UnknownColumn;
use SqlSemantics\Platform\MySql\Statement\Alter\TruncateTable;
use SqlSemantics\Platform\MySql\Statement\Call\Clock;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
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
use SqlSemantics\Platform\MySql\Statement\Name\AliasRule;
use SqlSemantics\Platform\MySql\Statement\Name\AmbiguousAlias;
use SqlSemantics\Platform\MySql\Statement\Name\InvalidProjectionAlias;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\AggregateInOrdering;
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
 * statements, has its own dispatcher. An unmapped semantic diagnostic is an
 * implementation failure, not an SQL refusal that a caller can accept as a result.
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 *
 * @visibility MySqlMemory
 */
final class Errors
{
    /**
     * Answers the server error of a diagnostic, for a name read in a clause of a statement.
     *
     * @throws SqlError When an earlier dependency or definer condition fails
     * @throws LogicException When no implementation maps the semantic diagnostic
     */
    public function error(Diagnostic $diagnostic, Session $session, string $clause = 'field list', ?Node $statement = null): SqlError
    {
        $database = $session->variables->database;
        if ($diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedCreateTable) {
            return $this->error($diagnostic->problem, $session, $clause, $statement);
        }
        if ($diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\InvalidColumnAttribute && $statement !== null && \MySqlMemory\Command\Dispatcher::program($statement)) {
            return (new TypeErrors())->columnAttribute($diagnostic);
        }
        if ($diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize) {
            return $diagnostic->scope !== null && $diagnostic->scope->schema === null && $database === ''
                ? QueryError::NoDatabase->error()
                : (new TypeErrors())->size($diagnostic, $session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651);
        }
        if ($diagnostic instanceof TooBigPrecision && $session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651) {
            return new SqlError(SchemaError::TooBigPrecision, sprintf("Too big precision %d specified for column '%s'. Maximum is %d.", $diagnostic->precision, $diagnostic->function, $diagnostic->maximum));
        }

        return (new \MySqlMemory\Session\Parse\Entry\InputErrors())->error($diagnostic, $session)
            ?? $this->unopened($diagnostic, $session, $statement)
            ?? $this->names($diagnostic, $database, $clause, $statement, $session->settings()->release())
            ?? $this->query($diagnostic, $clause)
            ?? $this->call($diagnostic, $session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651)
            ?? $this->database($diagnostic, $session->settings()->release())
            ?? $this->definition($diagnostic, $database, $session->settings()->legacy())
            ?? $this->manipulation($diagnostic, $statement)
            ?? $this->expression($diagnostic, $session->settings()->legacy())
            ?? $this->account($diagnostic, $session)
            ?? $this->server($diagnostic, $session, $statement)
            ?? throw new LogicException('No MySQL error mapping for ' . $diagnostic::class . ': ' . $diagnostic->message());
    }

    /**
     * Answers the server error of a located problem: an error made where it was located, a call of a function the server does not find, a clock call whose precision is above 6, or a diagnostic of a name read in a clause.
     *
     * A problem of a column of MATCH is followed by the error of its AGAINST (ER_WRONG_ARGUMENTS).
     */
    public function located(Diagnostic|FunctionCall|SqlError $problem, string $clause, bool $matched, Session $session, Node $statement): SqlError
    {
        $error = match (true) {
            $problem instanceof SqlError => $problem,
            $problem instanceof FunctionCall => $this->routine($problem, $session),
            default => $this->error($problem, $session, $clause, $statement),
        };

        $error = $matched ? new SqlError($error->error, $error->getMessage(), null, [[StatementError::WrongArguments->value, StatementError::WrongArguments->message('AGAINST')]]) : $error;

        return (new \MySqlMemory\Command\Replication\PurgeErrors())->prepared($error, $statement, $problem instanceof FunctionCall && ($problem->schema !== null || $session->variables->database !== ''));
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
    public function names(Diagnostic $diagnostic, string $database, string $clause, ?Node $statement, \SqlSemantics\Contract\GrammarRelease $release = \SqlSemantics\Contract\GrammarRelease::MySql847): ?SqlError
    {
        return match (true) {
            $diagnostic instanceof MissingTable => $diagnostic->name->schema === null && $database === '' ? QueryError::NoDatabase->error() : QueryError::NoSuchTable->error($diagnostic->name->schema->value ?? $database, $diagnostic->name->name->value),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Name\MissingTableQualifier => QueryError::UnknownTable->error(($diagnostic->qualifier->schema === null || $release === \SqlSemantics\Contract\GrammarRelease::MySql910 ? '' : $diagnostic->qualifier->schema->value . '.') . $diagnostic->qualifier->name->value, $clause),
            $diagnostic instanceof MissingColumn => QueryError::BadField->error((new \MySqlMemory\Error\DiagnosticText())->bytes(($diagnostic->qualifier?->schema === null ? '' : $diagnostic->qualifier->schema->value . '.') . ($diagnostic->qualifier === null ? '' : $diagnostic->qualifier->name->value . '.') . $diagnostic->name->value, 192), $this->joining($diagnostic, $statement) ? 'from clause' : $clause),
            $diagnostic instanceof AmbiguousColumn => QueryError::NonUniqueColumn->error(($diagnostic->qualifier?->schema === null ? '' : $diagnostic->qualifier->schema->value . '.') . ($diagnostic->qualifier === null ? '' : $diagnostic->qualifier->name->value . '.') . $diagnostic->name->value, $clause),
            $diagnostic instanceof AmbiguousAlias => QueryError::NonUniqueColumn->error($diagnostic->name->value, $clause),
            $diagnostic instanceof InvalidProjectionAlias => $diagnostic->rule === AliasRule::Ambiguous ? QueryError::NonUniqueColumn->error($diagnostic->name->value, $clause) : QueryError::IllegalReference->error($diagnostic->name->value, $diagnostic->rule->value),
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
            $diagnostic instanceof OrdinalOutOfRange => $this->ordinal($diagnostic, $clause),
            $diagnostic instanceof NonUniqueTable => QueryError::NonUniqueTable->error($diagnostic->alias->value),
            $diagnostic instanceof UndeclaredVariable => ProgramError::UndeclaredVariable->error($diagnostic->name->value),
            $diagnostic instanceof Misuse => $this->misuse($diagnostic),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\SelectOptionConflict => $diagnostic->repeated() ? StatementError::DuplicateArgument->error($diagnostic->first->value) : StatementError::WrongUsage->error($diagnostic->first->value, $diagnostic->second->value),
            $diagnostic instanceof AggregateInOrdering => new SqlError($diagnostic->distinct ? QueryError::AggregateInDistinctOrder : QueryError::AggregateInOrder, $diagnostic->message()),
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
     * Reports a position using its written name and its grouping or ordering role.
     */
    public function ordinal(OrdinalOutOfRange $diagnostic, string $clause): SqlError
    {
        return QueryError::BadField->error($diagnostic->name(), $diagnostic->grouped || $clause === 'group statement' ? 'group statement' : 'order clause');
    }

    /**
     * Answers the server error of a wrong call of a native function, or null for another diagnostic.
     */
    public function call(Diagnostic $diagnostic, bool $originalCase = false): ?SqlError
    {
        return (new CallErrors())->error($diagnostic, $originalCase);
    }

    /**
     * Answers the server error of a problem of a table or view definition, or null for another diagnostic.
     */
    public function definition(Diagnostic $diagnostic, string $database, bool $legacy = false): ?SqlError
    {
        return match (true) {
            $diagnostic instanceof RepeatedTable => QueryError::NonUniqueTable->error($diagnostic->name->name->value),
            $diagnostic === \SqlSemantics\Platform\MySql\Statement\View\Problem\BodyRestriction::Variable => SchemaError::ViewSelectVariable->error(),
            $diagnostic === \SqlSemantics\Platform\MySql\Statement\View\Problem\BodyRestriction::Into => SchemaError::ViewSelectClause->error('INTO'),
            $diagnostic instanceof MultiplePrimaryKeys => SchemaError::MultiplePrimaryKey->error(),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\InvalidColumnAttribute => (new TypeErrors())->columnAttribute($diagnostic),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Partition\Problem\MissingPartitions => \MySqlMemory\Error\Family\PartitionError::PartitionsNotDefined->error($diagnostic->kind->value),
            $diagnostic instanceof NoColumns => $legacy ? SchemaError::TableMustHaveColumns->error() : SchemaError::NoVisibleColumn->error(),
            $diagnostic instanceof DuplicateColumn, $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\DuplicateKeyColumn => SchemaError::DuplicateFieldName->error($diagnostic->column->value),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Alter\Problem\DuplicateColumn => SchemaError::DuplicateFieldName->error($diagnostic->column->value),
            $diagnostic instanceof IncorrectColumnName => SchemaError::WrongColumnName->error($diagnostic->column->value),
            $diagnostic instanceof NullablePrimaryKey => SchemaError::PrimaryCantHaveNull->error(),
            $diagnostic instanceof UnknownKeyColumn => SchemaError::KeyColumnMissing->error($diagnostic->column->value),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\UnknownIndexParser => AdministrationError::FunctionNotDefined->error($diagnostic->parser->value),
            $diagnostic instanceof UnknownColumn => SchemaError::FieldNotFoundInPartitionFunction->error(),
            $diagnostic instanceof TableExists => SchemaError::TableExists->error($diagnostic->name->name->value),
            $diagnostic instanceof UnknownAlterChoice => ($diagnostic->lock ? SchemaError::UnknownAlterLock : SchemaError::UnknownAlterAlgorithm)->error($diagnostic->name->value),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\WrongRelationKind => (new \MySqlMemory\Error\ProgramErrors())->relation($diagnostic, $database, $legacy),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\ViewColumnCount => SchemaError::ViewWrongList->error(),
            default => (new Definition\IndexShapes())->error($diagnostic),
        };
    }

    /**
     * Formats a database-option diagnostic retained by semantic derivation.
     */
    public function database(Diagnostic $diagnostic, \SqlSemantics\Contract\GrammarRelease $release = \SqlSemantics\Contract\GrammarRelease::MySql847): ?SqlError
    {
        return match (true) {
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\ReadOnlyConflict => SchemaError::ConflictingDeclarations->error('READ ONLY=', '0', 'READ ONLY=', '1'),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\CharsetConflict => SchemaError::ConflictingDeclarations->error('CHARACTER SET ', $diagnostic->earlier->nameIn($release), 'CHARACTER SET ', $diagnostic->later->nameIn($release)),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\InvalidEncryption => DataError::WrongValue->error('argument (should be Y or N)', (new \MySqlMemory\Error\DiagnosticText())->bytes(explode("\0", $diagnostic->value->value, 2)[0], 128)),
            default => null,
        };
    }

    /**
     * Answers the server error of a problem of a data manipulation statement, or null for another diagnostic.
     */
    public function manipulation(Diagnostic $diagnostic, ?Node $statement): ?SqlError
    {
        return match (true) {
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Problem\Read\OrderingPosition => new SqlError(QueryError::BadField, $diagnostic->message()),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadConflict => new SqlError(StatementError::WrongUsage, $diagnostic->message()),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert\InsertGrouping => $diagnostic->windowName() === null ? QueryError::InvalidGroupFunctionUse->error() : \MySqlMemory\Error\Family\ConstraintError::WindowFunctionContext->error($diagnostic->windowName()),
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
    public function expression(Diagnostic $diagnostic, bool $legacy = false): ?SqlError
    {
        return match (true) {
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidJsonDocument => DataError::InvalidJsonType->error(1, 'json_value'),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidJsonPath => DataError::InvalidJsonPath->error($diagnostic->position),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize => $this->typeSize($diagnostic),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\InvalidCharacterString => DataError::InvalidCharacterString->error($diagnostic->charset, strtoupper(bin2hex($diagnostic->bytes))),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedTemporalLiteral => DataError::WrongValue->error($diagnostic->form === \SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm::Timestamp ? 'DATETIME' : $diagnostic->form->value, substr($diagnostic->text, 0, 128)),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\TimeZoneConversion => $diagnostic->operand ? DataError::TimeZoneCast->error() : DataError::UnknownTimeZone->error($diagnostic->expression->zone->value),
            $diagnostic instanceof OperandColumns => QueryError::OperandColumns->error($diagnostic->expected),
            $diagnostic instanceof NotSupportedYet => StatementError::NotSupportedYet->error($diagnostic->feature),
            $diagnostic instanceof IllegalCollationMix => new SqlError(match (count($diagnostic->operands)) {
                2 => DataError::CantAggregateTwoCollations,
                3 => DataError::CantAggregateThreeCollations,
                default => DataError::CantAggregateCollations,
            }, $diagnostic->message()),
            $diagnostic instanceof UnknownCollation => SchemaError::UnknownCollation->error((new \MySqlMemory\Error\DiagnosticText())->bytes(explode("\0", $diagnostic->name, 2)[0], 64, $legacy)),
            $diagnostic instanceof TooBigPrecision => SchemaError::TooBigPrecision->error($diagnostic->precision, $diagnostic->function, $diagnostic->maximum),
            $diagnostic instanceof UnknownCharset => (new TypeErrors())->charset($diagnostic->name, $legacy),
            $diagnostic instanceof CollationMismatch => SchemaError::CollationCharsetMismatch->error($diagnostic->collation, $diagnostic->charset),
            default => null,
        };
    }

    /**
     * Answers the error of a declared type's length, precision or scale.
     */
    public function typeSize(\SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize $diagnostic): SqlError
    {
        return (new TypeErrors())->size($diagnostic);
    }

    /**
     * Answers the server error of a problem of a server, variable, program or utility statement, or null for another diagnostic.
     *
     * SHOW PROCEDURE CODE and SHOW FUNCTION CODE need a server built with debugging.
     *
     * A utility misuse of EXPLAIN carries no format name: an unknown format is reported without
     * its name, EXPLAIN ANALYZE with TRADITIONAL as ER_NOT_SUPPORTED_YET, and EXPLAIN INTO with a
     * format other than JSON as the implicit-format refusal (ER_EXPLAIN_INTO_IMPLICIT_FORMAT_NOT_SUPPORTED).
     */
    public function server(Diagnostic $diagnostic, Session $session, ?Node $statement): ?SqlError
    {
        return match (true) {
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplainInto,
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedConnectionExplain,
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Problem\InvalidExplainTarget => (new \MySqlMemory\Session\Parse\Entry\ExplainInput())->error($diagnostic),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Problem\UnknownExplainFormat => StatementError::UnknownExplainFormat->error($diagnostic->name->value),
            $diagnostic instanceof BucketCountOutOfRange => DataError::DataOutOfRange->error('Number of buckets', 'ANALYZE TABLE'),
            $diagnostic instanceof UnknownSystemVariable => $this->unknownVariable($diagnostic, $session->settings()->legacy()),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnstructuredVariable => AdministrationError::VariableIsNotStruct->error($diagnostic->name),
            $diagnostic instanceof VariableMisuse => new SqlError(ErrorNumbers::from($diagnostic->rule->code()), $diagnostic->message()),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Problem\LanguageBodyMismatch, $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ScopedLocalAssignment => $this->programText($diagnostic, $session),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ParameterScopeMismatch => StatementError::UnsupportedPreparedStatement->error(),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem\OperandWidth => (new \MySqlMemory\Session\Parse\Entry\Event\OperandConditions())->error($diagnostic, $session->settings()->legacy()),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityMisuse && $diagnostic->rule === \SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityRule::UnknownExplainFormat => StatementError::UnknownExplainFormat->error(''),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityMisuse && $diagnostic->rule === \SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityRule::AnalyzeFormat => StatementError::NotSupportedYet->error('EXPLAIN ANALYZE with TRADITIONAL format'),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityMisuse && $diagnostic->rule === \SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityRule::ExplainIntoFormat => StatementError::ExplainIntoImplicitFormat->error(),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Alter\Problem\PartitionEntryRefused => new SqlError(StatementError::ParseError, "Partitioning can not be used stand-alone in query near '' at line 1"),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Partition\Problem\InvalidPartitionExpression => new SqlError(StatementError::ParseError, $diagnostic->message()),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem => (new \MySqlMemory\Error\ProgramErrors())->error($diagnostic, $session->settings()->release()),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityMisuse && $diagnostic->rule === \SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityRule::DebugOnly => StatementError::FeatureDisabled->error('SHOW PROCEDURE|FUNCTION CODE', '--with-debug'),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityMisuse && $diagnostic->rule === \SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityRule::NamesShadow => ProgramError::VariableShadow->error('names'),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityMisuse && $diagnostic->rule === \SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityRule::NamesExpression => StatementError::ParseError->error('', 1),
            \MySqlMemory\Command\Admin\Refusals::handles($diagnostic) => (new \MySqlMemory\Command\Admin\Refusals())->error($diagnostic, $statement, $session->text, $session->settings()->legacy()),
            default => null,
        };
    }

    /**
     * Answers the server error of an account statement the analysis refuses before any catalog
     * lookup: a user attribute that is not a JSON object (ER_INVALID_USER_ATTRIBUTE_JSON), a
     * privilege at a level that cannot hold it, a column privilege on a column the table lacks
     * (ER_BAD_FIELD_ERROR), a password hash ALTER USER does not accept (ER_PARSE_ERROR), and the
     * role and rename contradictions below, all verified on an 8.4.7 server.
     *
     * CREATE ROLE fails with ER_CANNOT_USER for the anonymous user before any other role, then for
     * the first account it would define twice. GRANT of roles refuses an anonymous grantee first
     * (ER_ANONYMOUS_USER), then, role by role, a role granted to itself (ER_FAILED_ROLE_GRANT loop)
     * or an anonymous role (ER_FAILED_ROLE_GRANT). RENAME USER fails with ER_CANNOT_USER for the
     * source of the first pair that cannot succeed: a pair renaming an account to itself, a source
     * an earlier pair renamed away, or a destination an earlier pair created.
     */
    public function account(Diagnostic $diagnostic, Session $session): ?SqlError
    {
        $names = new \MySqlMemory\Command\Account\Names($session->settings()->release());
        if ($diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Account\Problem\InvalidUserAttribute) {
            return AccountError::InvalidUserAttributeJson->error();
        }
        if ($diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Account\Problem\MisplacedPrivilege) {
            return (new \MySqlMemory\Session\Parse\Entry\PrivilegeInput())->placement($diagnostic, $session);
        }
        if ($diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Account\Problem\UnknownGrantColumn) {
            return QueryError::BadField->error($diagnostic->column->value, $diagnostic->table->name->value);
        }
        if ($diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Account\Problem\RejectedPasswordHash) {
            return (new \MySqlMemory\Session\Syntax(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::named((string) $session->variables->read('character_set_client'))))->near(strlen($session->text), $session->text, $session->following, new \RuntimeException($diagnostic->message()), true, $session->semantics()->parser());
        }
        if ($diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Account\Problem\Role\RefusedCreateRole) {
            $seen = [];
            foreach ($diagnostic->roles as $role) {
                if ($role->username() === '') {
                    return $names->failure('CREATE ROLE', 'anonymous user');
                }
            }
            foreach ($diagnostic->roles as $role) {
                $identity = new \MySqlMemory\Account\Identity($role->username(), $role->hostname() ?? '%');
                $key = mb_strtolower($identity->key(), 'UTF-8');
                if (isset($seen[$key])) {
                    return $names->failure('CREATE ROLE', $seen[$key]->quoted());
                }
                $seen[$key] = $identity;
            }
        }
        if ($diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Account\Problem\Role\RefusedGrantRoles) {
            $users = [];
            foreach ($diagnostic->users as $user) {
                if ($user instanceof \SqlSemantics\Platform\MySql\Statement\Name\AccountName) {
                    if ($user->username() === '') {
                        return AccountError::AnonymousRoleGrantee->error();
                    }
                    $users[] = new \MySqlMemory\Account\Identity($user->username(), $user->hostname() ?? '%');
                }
            }
            foreach ($diagnostic->roles as $granted) {
                $role = new \MySqlMemory\Account\Identity($granted->role->username(), $granted->role->hostname() ?? '%');
                foreach ($users as $user) {
                    if (mb_strtolower($user->key(), 'UTF-8') === mb_strtolower($role->key(), 'UTF-8')) {
                        return AccountError::RoleGrantedToItself->error($user->backquoted(), $role->backquoted());
                    }
                }
                if ($role->user === '') {
                    return AccountError::FailedRoleGrant->error($role->backquoted(), ($users[0] ?? $role)->backquoted());
                }
            }
        }
        if ($diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Account\Problem\User\RefusedRenameUser) {
            $gone = [];
            $made = [];
            $first = null;
            foreach ($diagnostic->renamings as $renaming) {
                if (!$renaming->from instanceof \SqlSemantics\Platform\MySql\Statement\Name\AccountName || !$renaming->to instanceof \SqlSemantics\Platform\MySql\Statement\Name\AccountName) {
                    continue;
                }
                $from = new \MySqlMemory\Account\Identity($renaming->from->username(), $renaming->from->hostname() ?? '%');
                $to = new \MySqlMemory\Account\Identity($renaming->to->username(), $renaming->to->hostname() ?? '%');
                $first ??= $from;
                [$source, $destination] = [mb_strtolower($from->key(), 'UTF-8'), mb_strtolower($to->key(), 'UTF-8')];
                if ($source === $destination || isset($gone[$source]) || isset($made[$destination])) {
                    return $names->failure('RENAME USER', $from->quoted());
                }
                unset($made[$source], $gone[$destination]);
                $gone[$source] = true;
                $made[$destination] = true;
            }
            return $names->failure('RENAME USER', ($first ?? $session->variables->definer)->quoted());
        }

        return null;
    }

    /**
     * Answers the syntax error of a program body the parser refuses: a routine body in a language the
     * server does not read, or a local variable assignment carrying a system variable scope. MySQL 5.6
     * reports the scoped assignment after the assignment; later releases report it at the variable.
     */
    public function programText(\SqlSemantics\Platform\MySql\Statement\Routine\Problem\LanguageBodyMismatch|\SqlSemantics\Platform\MySql\Statement\Routine\Problem\ScopedLocalAssignment $diagnostic, Session $session): SqlError
    {
        $offset = $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ScopedLocalAssignment && $session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651 ? $diagnostic->end : $diagnostic->offset;

        return (new \MySqlMemory\Session\Syntax(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::named((string) $session->variables->read('character_set_client'))))->near($offset ?? strlen($session->text), $session->text, $session->following, new \RuntimeException($diagnostic->message()), true, $session->semantics()->parser());
    }

    /**
     * Formats the retained variable name within the server diagnostic's byte limit.
     */
    public function unknownVariable(UnknownSystemVariable $diagnostic, bool $legacy): SqlError
    {
        $name = (new \MySqlMemory\Error\DiagnosticText())->bytes(explode("\0", $diagnostic->name, 2)[0], 64, $legacy);
        $message = AdministrationError::UnknownSystemVariable->message($name);

        return new SqlError(AdministrationError::UnknownSystemVariable, $message, following: $diagnostic->assigned && !$diagnostic->structured && $legacy ? [[AdministrationError::UnknownSystemVariable->value, $message]] : []);
    }

    /**
     * Answers the server error of a rule a data manipulation statement breaks.
     */
    public function write(WriteMisuse $misuse, ?Node $statement): SqlError
    {
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain) {
            return $this->write($misuse, $statement->statement);
        }
        return match ($misuse->rule) {
            WriteRule::DefaultOutsideInsert => QueryError::ValuesDefault->error(),
            WriteRule::OrderedMultipleUpdate => StatementError::WrongUsage->error('UPDATE', 'ORDER BY'),
            WriteRule::LimitedMultipleUpdate => StatementError::WrongUsage->error('UPDATE', 'LIMIT'),
            WriteRule::SampledWrite => StatementError::FeatureNotSupported->error('TABLESAMPLE'),
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
            MisuseRule::JsonTableSubquery => StatementError::WrongArguments->error('JSON_TABLE'),
            MisuseRule::TableFunctionWithoutAlias => QueryError::TableFunctionWithoutAlias->error(),
            MisuseRule::DuplicateAlias, MisuseRule::DuplicateCommonTable => QueryError::NonUniqueTable->error($name),
            MisuseRule::RecursiveWithoutUnion => QueryError::RecursiveRequiresUnion->error($name),
            MisuseRule::RecursiveWithoutAnchor => QueryError::RecursiveRequiresNonrecursiveFirst->error($name),
            MisuseRule::RecursiveReferencePosition => QueryError::RecursiveWithoutUnion->error($name),
            MisuseRule::DuplicateColumn => SchemaError::DuplicateFieldName->error($name),
            MisuseRule::DuplicateWindow => QueryError::WindowDefinedTwice->error($name),
            MisuseRule::WindowCircularity => QueryError::WindowCircularity->error(),
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
     * Answers the error of a call of a stored function that does not exist, in the database the call names or the current one.
     */
    public function routine(FunctionCall|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredCall $call, Session $session): SqlError
    {
        $database = $session->variables->database;
        if ($call->schema === null && $database === '') {
            if ($session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651) {
                return ProgramError::RoutineMissing->error('FUNCTION', $call->name->value);
            }
            $error = QueryError::NoDatabase;

            return new SqlError($error, $error->message(), following: $session->settings()->legacy() ? [] : [[$error->number(), $error->message()]]);
        }

        $function = $session->instance->dictionary->schema($call->schema->value ?? $database)->functions[strtolower($call->name->value)] ?? null;
        if ($function !== null) {
            $count = $function->parameterCount();

            return $count === count($call->arguments) ? StatementError::NotSupportedYet->error('calls of stored functions') : ProgramError::RoutineArgumentCount->error('FUNCTION', $function->schema . '.' . $function->name, $count, count($call->arguments));
        }

        return $call->keywordName($session->settings()->release()) ? ProgramError::FunctionNameCollision->error(($call->schema->value ?? $database) . '.' . $call->name->value) : ProgramError::RoutineMissing->error('FUNCTION', ($call->schema->value ?? $database) . '.' . $call->name->value);
    }
}
