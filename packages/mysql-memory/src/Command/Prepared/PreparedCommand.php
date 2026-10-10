<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Prepared;

use MySqlMemory\Command\Command;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Problems;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Deallocate;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Execute;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Prepare;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Operation;

/**
 * Executes PREPARE, EXECUTE and DEALLOCATE PREPARE: the prepared statements of a session, named in SQL.
 *
 * PREPARE first forgets a statement of the same name, then parses and resolves the text, a
 * string or the value of a user variable (NULL is the text `NULL`), as one statement; its `?`
 * markers are its parameters. A text of several statements is a syntax error at the second, and
 * the prepared statement statements cannot themselves be prepared
 * (ER_UNSUPPORTED_PS). EXECUTE binds one user variable to each marker
 * (ER_WRONG_ARGUMENTS otherwise) and runs the statement as the prepared statement protocol does.
 * Names are not case-sensitive; an unknown one is ER_UNKNOWN_STMT_HANDLER.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/sql-prepared-statements.html.
 *
 * @visibility MySqlMemory
 */
final class PreparedCommand implements Command
{
    /**
     * Answers true.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Prepares, executes or forgets the statement.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        if ($statement instanceof Deallocate) {
            if (!isset($session->preparation->named[strtolower($statement->name->value)])) {
                throw StatementError::UnknownStatementHandler->error($statement->name->value, 'DEALLOCATE PREPARE');
            }
            \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_stmt_close');
            unset($session->preparation->named[strtolower($statement->name->value)]);

            return new Completion();
        }
        if ($statement instanceof Execute) {
            return $this->run($statement, $session);
        }
        assert($statement instanceof Prepare);
        \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_stmt_prepare');
        $key = strtolower($statement->name->value);
        unset($session->preparation->named[$key]);
        if ($statement->source instanceof Text) {
            $text = $statement->source->value;
        } else {
            [$value, $domain] = $session->variables->user($statement->source->name->value);
            $text = $value === null ? 'NULL' : (string) Convert::toText($value, $domain);
        }
        $statements = $session->split($text);
        if (count($statements) > 1) {
            $offset = (int) strpos($text, ltrim($statements[1]), strlen($statements[0]));

            throw StatementError::ParseError->error(mb_strcut(substr($text, $offset), 0, 80, 'UTF-8'), substr_count(substr($text, 0, $offset), "\n") + 1);
        }
        $prepared = $session->analyze($text, true);
        if ($prepared->statement instanceof Prepare || $prepared->statement instanceof Execute || $prepared->statement instanceof Deallocate) {
            throw StatementError::UnsupportedPreparedStatement->error();
        }
        (new \MySqlMemory\Hint\Hints())->prepare($prepared, $session);
        (new Problems())->raise($prepared, $session);
        $parameters = count(array_filter($session->semantics()->parser()->tokenize($text), static fn ($token): bool => $token->name === 'PARAM_MARKER'));
        $session->preparation->named[$key] = [$text, $parameters, ParameterBindings::capture($prepared)];

        return new Completion(0, 0, 0, 'Statement prepared');
    }

    /**
     * Runs a prepared statement with the values of the user variables EXECUTE names.
     *
     * @throws \MySqlMemory\Error\SqlError When the statement is unknown, the variables do not match its markers, or it fails
     */
    public function run(Execute $statement, Session $session): Reply
    {
        $prepared = $session->preparation->named[strtolower($statement->name->value)] ?? null;
        if ($prepared === null) {
            throw StatementError::UnknownStatementHandler->error($statement->name->value, 'EXECUTE');
        }
        [$text, $count, $types] = $prepared;
        \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_stmt_execute');
        if (count($statement->variables) !== $count) {
            throw StatementError::WrongArguments->error('EXECUTE');
        }
        $parameters = [];
        foreach ($statement->variables as $variable) {
            $parameters[] = $this->parameter($session, ...$session->variables->user($variable->name->value));
        }
        if (version_compare($session->instance->version, '8.0.22', '>=') && $types->changed($parameters)) {
            \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_stmt_reprepare');
            \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_stmt_prepare');
        }

        $previous = $session->preparation->domains;
        $session->preparation->domains = version_compare($session->instance->version, '8.0.22', '>=') ? $types->domains() : [];
        try {
            return $session->execute($text, $parameters, true);
        } finally {
            $session->preparation->domains = $previous;
        }
    }

    /**
     * Answers the value and type a user variable binds to a parameter marker.
     *
     * An integer binds a BIGINT, a decimal a DECIMAL(65,30), a float a DOUBLE, a binary string a
     * VARBINARY(65535), and another string a VARCHAR(16383) in the collation of the connection,
     * converted to it; NULL binds the type of a marker no value is bound to (verified on a live 8.4
     * server).
     *
     * @return array{int|float|string|null, \MySqlMemory\Typing\Domain}
     */
    public function parameter(Session $session, int|float|string|null $value, \MySqlMemory\Typing\Domain $domain): array
    {
        if (version_compare($session->instance->version, '8.0.0', '<')) {
            return $this->legacyParameter($value, $domain);
        }
        $connection = \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::named((string) $session->variables->read('collation_connection')) ?? \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_0900_ai_ci');
        $kind = $domain->kind;
        if ($value === null || $kind === Kind::Null) {
            return [null, \MySqlMemory\Typing\Domain::string(16383, $connection)->withNullable(true)];
        }

        return match (true) {
            $kind === Kind::Integer => [$value, \MySqlMemory\Typing\Domain::integer(Field::LongLong, 21, $domain->unsigned)],
            $kind === Kind::Decimal => [$value, \MySqlMemory\Typing\Domain::decimal(65, 30)],
            $kind === Kind::Double => [$value, \MySqlMemory\Typing\Domain::double(23)],
            $domain->collation->bytes() => [$value, \MySqlMemory\Typing\Domain::string(65535, $domain->collation)],
            default => [\MySqlMemory\Value\Encoding::convert((string) $value, $domain->collation->charset, $connection->charset), \MySqlMemory\Typing\Domain::string(16383, $connection)],
        };
    }

    /**
     * Binds a SQL user variable as MySQL 5.x does: result metadata uses VAR_STRING,
     * while expression evaluation retains the variable's numeric kind and precision.
     * Observed through SQL PREPARE on MySQL 5.6.51, including NULL and type changes.
     *
     * @return array{int|float|string|null, \MySqlMemory\Typing\Domain}
     */
    public function legacyParameter(int|float|string|null $value, \MySqlMemory\Typing\Domain $domain): array
    {
        if ($value === null) {
            return [null, new \MySqlMemory\Typing\Domain(Kind::Null, Field::VarString)];
        }
        $length = match ($domain->kind) {
            Kind::Integer => 21,
            Kind::String => mb_strlen((string) $value, \MySqlMemory\Value\Encoding::name($domain->collation->charset) ?? '8bit'),
            Kind::Decimal => strlen(ltrim((string) $value, '-')) + 1,
            Kind::Double, Kind::Date, Kind::Time, Kind::DateTime, Kind::Year, Kind::Json, Kind::Bit, Kind::Null => $domain->length,
        };

        return [$value, new \MySqlMemory\Typing\Domain($domain->kind, Field::VarString, $length, $domain->decimals, $domain->unsigned, $domain->collation, $domain->kind === Kind::Decimal)];
    }
}
