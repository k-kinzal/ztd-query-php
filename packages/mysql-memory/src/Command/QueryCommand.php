<?php

declare(strict_types=1);

namespace MySqlMemory\Command;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Plan\QueryPlan;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Session;
use MySqlMemory\Typing\Domain;
use Override;
use SqlSemantics\Contract\GrammarRelease;
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
 * A query whose INTO names variables of a stored program computes its values as a write does,
 * so that a warning is an error under a strict sql_mode, and stores each value as into a column
 * of the variable's type (verified on a live 8.4 server).
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
        (new \MySqlMemory\Session\Problem\Sampling())->optimized($statement, $operation->facts, $planner->settings, $planner->dictionary);
        if ($into !== null && !$into instanceof IntoVariables) {
            $this->file($into, $context, $session->settings()->legacy());
        }
        if ($session->settings()->release() === GrammarRelease::MySql910 && $this->unites($statement) && $plan->domains !== []) {
            $nullable = $plan->domains[0]->nullable;
            $plan = new QueryPlan($plan->root, array_map(static fn (Domain $domain): Domain => $domain->withNullable($nullable), $plan->domains), $plan->names, $plan->origins);
        }
        $restricted = $session->settings()->release() === GrammarRelease::MySql847 ? $this->restricted($statement, $operation->facts) : null;
        if ($restricted !== null && count($restricted) === count($plan->domains)) {
            $plan = new QueryPlan($plan->root, array_map(static fn (Domain $domain, bool $nullable): Domain => $domain->withNullable($domain->nullable || $nullable), $plan->domains, $restricted), $plan->names, $plan->origins);
        }
        if ($into instanceof IntoVariables && array_filter($into->targets, static fn ($target): bool => !$target instanceof UserVariable) !== []) {
            $context->strict = $context->modes->strict();
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
     * Tells whether the outermost operation of a query is a UNION.
     *
     * MySQL 9.1 sends every column of such a query as NOT NULL exactly when its first column is,
     * whatever the other columns hold; a derived table, a view or a table it creates keeps the
     * nullability of each column, and INTERSECT and EXCEPT are not concerned (verified on a live
     * 9.1 server).
     */
    public function unites(Query $query): bool
    {
        while (true) {
            if ($query instanceof QueryStatement || $query instanceof \SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery) {
                $query = $query->query;
                continue;
            }
            if ($query instanceof \SqlSemantics\Platform\MySql\Statement\Query\QueryExpression) {
                $query = $query->body;
                continue;
            }

            return ($query instanceof \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation || $query instanceof \SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation)
                && $query->operator === \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator::Union;
        }
    }

    /**
     * Answers the nullability MySQL 8.4 sends for each column of a query whose outermost operation is INTERSECT or EXCEPT: that of any operand, as for UNION; null for another query.
     *
     * The rows come from the left operand only, but MySQL 8.4 sends a column NOT NULL only when it
     * is NOT NULL in every operand. A derived table, a common table expression or a subquery keeps
     * the nullability of the left operand, as MySQL 8.0 and 9.1 do for the result too (verified
     * on live 8.0.44, 8.4.7 and 9.1.0 servers).
     *
     * @return list<bool>|null
     */
    public function restricted(Query $query, \SqlSemantics\Statement\Fact\Facts $facts): ?array
    {
        $outermost = $this->outermost($query);
        if (!($outermost instanceof \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation || $outermost instanceof \SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation)
            || $outermost->operator === \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator::Union) {
            return null;
        }

        return $this->nullables($outermost, $facts);
    }

    /**
     * Answers whether each column of a query is nullable in any operand of its set operations, or in the query itself when it has none.
     *
     * @return list<bool>
     */
    public function nullables(Query|\SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion $query, \SqlSemantics\Statement\Fact\Facts $facts): array
    {
        $outermost = $query instanceof Query ? $this->outermost($query) : $query;
        if ($outermost instanceof \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation || $outermost instanceof \SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation || $outermost instanceof \SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion) {
            $left = $this->nullables($outermost->left, $facts);
            $right = $this->nullables($outermost->right, $facts);

            return array_map(static fn (bool $one, ?bool $other): bool => $one || $other === true, $left, array_pad($right, count($left), null));
        }

        return array_map(static fn ($slot): bool => $slot->nullability !== \SqlSemantics\Statement\Type\Nullability::NotNull, $facts->query($outermost)->shape->slots);
    }

    /**
     * Answers the query inside the statement, parentheses and WITH or ORDER BY wrappers around a query.
     */
    public function outermost(Query $query): Query
    {
        while ($query instanceof QueryStatement || $query instanceof \SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery || $query instanceof \SqlSemantics\Platform\MySql\Statement\Query\QueryExpression) {
            $query = $query instanceof \SqlSemantics\Platform\MySql\Statement\Query\QueryExpression ? $query->body : $query->query;
        }

        return $query;
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
            $this->file($into, $context, $session->settings()->legacy());
        }
        if (count($into->targets) !== count($result->columns)) {
            throw QueryError::WrongNumberOfColumnsInSelect->error();
        }
        if ($result->rows === []) {
            $context->warning(ProgramError::NoData);

            return new Completion(0, 0, $context->diagnostics->count());
        }
        foreach ($into->targets as $index => $target) {
            $column = $result->columns[$index];
            if (!$target instanceof UserVariable) {
                $variable = $session->program?->variable($target->name->value) ?? throw ProgramError::UndeclaredVariable->error($target->name->value);
                $variable->assign($result->rows[0][$index], $this->domain($column), $context);
                continue;
            }
            $session->variables->assign($target->name->value, $result->rows[0][$index], $this->domain($column));
        }
        if (count($result->rows) > 1) {
            throw QueryError::TooManyRows->error();
        }

        return new Completion(1, 0, $context->diagnostics->count());
    }

    /**
     * Refuses to write the rows of a query to a file, once the query is prepared and before it runs.
     *
     * The server checks the FIELDS options of INTO OUTFILE first: ENCLOSED BY and ESCAPED BY take at
     * most one character. It warns once about separators that hold bytes outside ASCII, and then
     * refuses the file, since the server runs with secure_file_priv limiting the files it writes.
     * MySQL 5.6 and 5.7 refuse the file before they check or warn about the separators (verified on
     * live 5.6.51 and 5.7.44 servers).
     * Source: https://dev.mysql.com/doc/refman/8.4/en/select-into.html,
     * https://dev.mysql.com/doc/refman/8.4/en/load-data.html,
     * https://dev.mysql.com/doc/refman/8.4/en/server-system-variables.html#sysvar_secure_file_priv.
     *
     * @throws \MySqlMemory\Error\SqlError Always: the separators are wrong or the file is refused
     */
    public function file(IntoDestination $into, Context $context, bool $legacy = false): never
    {
        if ($legacy) {
            throw StatementError::OptionPreventsStatement->error('--secure-file-priv');
        }
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
                throw StatementError::WrongFieldTerminators->error();
            }
        }
        if (preg_match('/[\x80-\xff]/', implode('', $bytes)) === 1) {
            $context->warning(DataError::NonAsciiSeparator);
        }

        throw StatementError::OptionPreventsStatement->error('--secure-file-priv');
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
