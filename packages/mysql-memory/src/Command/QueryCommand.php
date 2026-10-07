<?php

declare(strict_types=1);

namespace MySqlMemory\Command;

use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Result\Reply;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Result\Completion;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Session;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoDestination;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoVariables;
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
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
    #[\Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Plans and executes the query, answering its rows.
     */
    #[\Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof Query);
        $planner = new Planner($statement, $operation->facts, $session->settings(), $connection, $session->instance->dictionary);
        $into = $this->destination($statement);
        $result = (new Output())->result($planner->query($statement, null), $context);
        if ($into === null) {
            return $result;
        }

        return $this->into($into, $result, $session, $context);
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
            $directory = (string) $session->variables->read('secure_file_priv');
            throw ErrorCode::OptionPreventsStatement->error('--secure-file-priv');
        }
        if (count($into->targets) !== count($result->columns)) {
            throw ErrorCode::WrongNumberOfColumnsInSelect->error();
        }
        if (count($result->rows) > 1) {
            throw ErrorCode::TooManyRows->error();
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

        return new Completion(1, 0, $context->diagnostics->count());
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
            default => Domain::string(16777216, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::named('binary') ?? \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::binary(), Field::MediumBlob),
        };
    }
}
