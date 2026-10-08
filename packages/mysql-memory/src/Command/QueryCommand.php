<?php

declare(strict_types=1);

namespace MySqlMemory\Command;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Session;
use MySqlMemory\Typing\Domain;
use Override;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\FieldOption;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\FieldOptionKind;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LineOption;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\TextFileFormat;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoDestination;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoOutfile;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoVariables;
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Query;

/**
 * Executes a query: SELECT, a set operation, VALUES or TABLE.
 *
 * @visibility MySqlMemory
 */
final class QueryCommand implements Command
{
    /**
     * Answers true: a query starts with an empty diagnostics area.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Plans and executes the query, answering its rows.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof Query);
        $planner = new Planner($statement, $operation->facts, $session->settings(), $connection, $session->instance->dictionary);
        $into = $this->destination($statement);
        $plan = $planner->query($statement, null);
        if ($into !== null && !$into instanceof IntoVariables) {
            $this->file($into, $context);
        }
        $result = (new Output())->result($plan, $context, $this->calculates($statement));
        if ($into === null) {
            return $result;
        }

        return $this->into($into, $result, $session, $context);
    }

    /**
     * Tells whether the first SELECT of a query asks for SQL_CALC_FOUND_ROWS: FOUND_ROWS() then counts the rows before the LIMIT.
     *
     * Source: https://dev.mysql.com/doc/refman/8.4/en/information-functions.html#function_found-rows.
     */
    public function calculates(Query $query): bool
    {
        $first = $query;
        while ($first !== null && !$first instanceof Select) {
            $first = match (true) {
                $first instanceof QueryStatement, $first instanceof \SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery => $first->query,
                $first instanceof \SqlSemantics\Platform\MySql\Statement\Query\QueryExpression => $first->body,
                $first instanceof \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation && $first->left instanceof Query => $first->left,
                default => null,
            };
        }

        return $first instanceof Select && in_array(SelectOption::CalcFoundRows, $first->options, true);
    }

    /**
     * Finds the INTO destination of a query, written in the query or in the parentheses around it.
     */
    public function destination(Query $query): ?IntoDestination
    {
        while (true) {
            if (($query instanceof Select || $query instanceof QueryStatement) && $query->into !== null) {
                return $query->into;
            }
            if ($query instanceof \SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery || $query instanceof QueryStatement) {
                $query = $query->query;
                continue;
            }
            if ($query instanceof \SqlSemantics\Platform\MySql\Statement\Query\QueryExpression) {
                $query = $query->body;
                continue;
            }
            if ($query instanceof \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation) {
                $query = $query->right;
                continue;
            }

            return null;
        }
    }

    /**
     * Writes the rows of a query to its INTO destination and answers the completion.
     *
     * @throws \MySqlMemory\Error\SqlError When the destination refuses the rows
     */
    public function into(IntoDestination $into, ResultSet $result, Session $session, Context $context): Reply
    {
        if (!$into instanceof IntoVariables) {
            $this->file($into, $context);
        }
        if (count($into->targets) !== count($result->columns)) {
            throw ErrorCode::WrongNumberOfColumnsInSelect->error();
        }
        if ($result->rows === []) {
            $context->warning(ErrorCode::NoData);

            return new Completion(0, 0, $context->diagnostics->count());
        }
        foreach ($into->targets as $index => $target) {
            if (!$target instanceof UserVariable) {
                throw ErrorCode::UndeclaredVariable->error($target->name->value);
            }
            $column = $result->columns[$index];
            $session->variables->assign($target->name->value, $result->rows[0][$index], $this->domain($column));
        }
        if (count($result->rows) > 1) {
            throw ErrorCode::TooManyRows->error();
        }

        return new Completion(1, 0, $context->diagnostics->count());
    }

    /**
     * Refuses to write the rows of a query to a file, once the query is prepared and before it runs.
     *
     * The server checks the FIELDS options of INTO OUTFILE first: ENCLOSED BY and ESCAPED BY take at
     * most one character. It warns once about separators that hold bytes outside ASCII, and then
     * refuses the file, since the server runs with secure_file_priv limiting the files it writes.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/select-into.html,
     * https://dev.mysql.com/doc/refman/8.4/en/load-data.html,
     * https://dev.mysql.com/doc/refman/8.4/en/server-system-variables.html#sysvar_secure_file_priv.
     *
     * @throws \MySqlMemory\Error\SqlError Always: the separators are wrong or the file is refused
     */
    public function file(IntoDestination $into, Context $context): never
    {
        $format = $into instanceof IntoOutfile ? $into->format : null;
        $texts = $format instanceof TextFileFormat ? [...$format->fields, ...$format->lines] : [];
        $bytes = array_map(static fn (FieldOption|LineOption $option): string => match ($option->text->radix) {
            Radix::Hexadecimal => (string) hex2bin(strlen($option->text->value) % 2 === 1 ? '0' . $option->text->value : $option->text->value),
            Radix::Bit => implode('', array_map(static fn (string $octet): string => chr((int) bindec($octet)), $option->text->value === '' ? [] : str_split(str_pad($option->text->value, (int) ceil(strlen($option->text->value) / 8) * 8, '0', STR_PAD_LEFT), 8))),
            null => $option->text->value,
        }, $texts);
        foreach ($texts as $index => $option) {
            $single = $option instanceof FieldOption && $option->kind !== FieldOptionKind::Terminated;
            if ($single && ($option->text->radix === null ? mb_strlen($bytes[$index], 'UTF-8') : strlen($bytes[$index])) > 1) {
                throw ErrorCode::WrongFieldTerminators->error();
            }
        }
        if (preg_match('/[\x80-\xff]/', implode('', $bytes)) === 1) {
            $context->warning(ErrorCode::NonAsciiSeparator);
        }

        throw ErrorCode::OptionPreventsStatement->error('--secure-file-priv');
    }

    /**
     * Answers the domain a user variable holds a value of a result column in.
     */
    public function domain(\MySqlMemory\Result\ResultColumn $column): Domain
    {
        if ($column->type->integral()) {
            return Domain::integer(Field::LongLong, 21, $column->unsigned());
        }

        return match ($column->type) {
            Field::NewDecimal, Field::Decimal => Domain::decimal(65, $column->decimals),
            Field::Double, Field::Float => Domain::double(),
            Field::Tiny, Field::Short, Field::Long, Field::Null, Field::Timestamp, Field::LongLong, Field::Int24, Field::Date, Field::Time, Field::DateTime, Field::Year, Field::NewDate, Field::VarChar, Field::Bit, Field::Vector, Field::Json, Field::Enum, Field::Set, Field::TinyBlob, Field::MediumBlob, Field::LongBlob, Field::Blob, Field::VarString, Field::String, Field::Geometry => Domain::string(16777216, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::named('binary') ?? \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::binary(), Field::MediumBlob),
        };
    }
}
