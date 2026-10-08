<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Program;

use MySqlMemory\Command\Command;
use MySqlMemory\Dictionary\Routine;
use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Program\Activation;
use MySqlMemory\Program\Invocation;
use MySqlMemory\Program\Row;
use MySqlMemory\Program\Variable;
use MySqlMemory\Result\Batch;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use MySqlMemory\Typing\Domain;
use Override;
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterMode;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Operation;

/**
 * Executes CALL of a stored procedure.
 *
 * A missing procedure is ER_SP_DOES_NOT_EXIST, and another number of arguments than of
 * parameters ER_SP_WRONG_NO_OF_ARGS. A procedure that calls itself, even through another,
 * more times than max_sp_recursion_depth allows is ER_SP_RECURSION_LIMIT. An IN parameter holds
 * the value of its argument and an INOUT parameter the value of its variable, each stored as
 * into a column of its type; an OUT parameter starts NULL. The argument of an OUT or INOUT
 * parameter is a user variable, a variable of the calling program or a column of the NEW row
 * of a BEFORE trigger (ER_SP_NOT_VAR_ARG otherwise), which receives the value of the parameter
 * once the procedure ends, in the order of the parameters; a parameter marker of a prepared CALL
 * is accepted, and the value of its parameter is not sent back. The procedure answers the result
 * set of each query it runs, then the completion with the rows its last statement affected,
 * which ROW_COUNT() reads; when it fails, the result sets answered before come before the
 * error (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/call.html,
 * https://dev.mysql.com/doc/refman/8.4/en/server-system-variables.html#sysvar_max_sp_recursion_depth.
 *
 * @visibility MySqlMemory
 */
final class CallCommand implements Command
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
     * Runs the procedure.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof ProcedureCall);
        $routine = $this->routine($statement, $session);
        $caller = $session->program;
        $key = $routine->schema . '.' . $routine->name;
        $limit = (int) $session->variables->read('max_sp_recursion_depth');
        if ($caller !== null && $caller->depth('PROCEDURE', $key) > $limit) {
            throw ProgramError::RecursionLimit->error($limit, $routine->name);
        }
        $targets = $this->targets($statement, $routine, $caller);
        $bound = $this->arguments($statement, $routine, $operation, $session, $context, $connection, $targets);
        $activation = new Activation('PROCEDURE', $key, $caller !== null && $caller->contained, Collation::named($routine->charsets[2]) ?? Collation::known('utf8mb4_0900_ai_ci'), $caller);
        $invocation = new Invocation($session);
        $activation->scope[] = $parameters = $invocation->parameters($routine, $bound, $routine->mode);
        try {
            $invocation->run($activation, $routine->statement->body, $routine->schema, $routine->mode);
        } catch (SqlError $error) {
            if ($caller === null) {
                $session->running->replies = $activation->results;
            } else {
                array_push($caller->results, ...$activation->results);
            }
            throw $error;
        }
        $this->write($targets, $parameters, $session, $invocation->context((string) $session->variables->read('sql_mode')));
        $completion = new Completion(max(0, $session->variables->rowCount), 0, $session->diagnostics->count());

        return $activation->results === [] ? $completion : new Batch([...$activation->results, $completion]);
    }

    /**
     * Finds the procedure a CALL names and checks the number of its arguments.
     *
     * @throws SqlError When the procedure does not exist or the number of arguments is wrong
     */
    public function routine(ProcedureCall $statement, Session $session): Routine
    {
        $name = $statement->procedure->name->value;
        $database = ProgramSource::database($statement->procedure->schema, $session);
        $routine = $session->instance->dictionary->schema($database)->procedures[strtolower($name)] ?? null;
        if ($routine === null) {
            throw ProgramError::RoutineMissing->error('PROCEDURE', $database . '.' . $name);
        }
        $parameters = $routine->statement->parameters->parameters;
        if (count($parameters) !== count($statement->arguments)) {
            throw ProgramError::RoutineArgumentCount->error('PROCEDURE', $database . '.' . $name, count($parameters), count($statement->arguments));
        }

        return $routine;
    }

    /**
     * Answers the variable that receives the value of each OUT and INOUT parameter, by the position of the parameter: the name of a user variable, or a variable of the calling program.
     *
     * @return array<int, string|Variable>
     *
     * @throws SqlError When the argument of such a parameter is no variable (ER_SP_NOT_VAR_ARG)
     */
    public function targets(ProcedureCall $statement, Routine $routine, ?Activation $caller): array
    {
        $targets = [];
        foreach ($routine->statement->parameters->parameters as $index => $parameter) {
            if ($parameter->mode !== ParameterMode::Out && $parameter->mode !== ParameterMode::InOut) {
                continue;
            }
            $argument = $statement->arguments[$index];
            if ($argument instanceof Parameter) {
                continue;
            }
            $target = match (true) {
                $argument instanceof UserVariable => $argument->name->value,
                $argument instanceof ColumnUse && $argument->qualifier === null => $caller?->variable($argument->name->value),
                $argument instanceof ColumnUse && $argument->qualifier->schema === null => $this->field($caller, $argument->qualifier->name->value, $argument->name->value),
                default => null,
            };
            $targets[$index] = $target ?? throw ProgramError::NotVariableArgument->error($index + 1, $routine->schema . '.' . $routine->name);
        }

        return $targets;
    }

    /**
     * Finds the column of the NEW row of a BEFORE trigger an argument names, or null when it names none.
     */
    public function field(?Activation $caller, string $row, string $column): ?Variable
    {
        $found = $caller?->row($row);

        return $found !== null && $found->writable ? $found->variable($column) : null;
    }

    /**
     * Answers the value and type each parameter starts with: the value of the argument of an IN parameter, the value of the variable of an INOUT parameter, and NULL for an OUT parameter.
     *
     * @param array<int, string|Variable> $targets The variables of the OUT and INOUT parameters
     * @return list<array{int|float|string|null, Domain}>
     *
     * @throws SqlError When an argument is refused
     */
    public function arguments(ProcedureCall $statement, Routine $routine, Operation $operation, Session $session, Context $context, Connection $connection, array $targets): array
    {
        $planner = new Planner($statement, $operation->facts, $session->settings(), $connection, $session->instance->dictionary);
        $bound = [];
        foreach ($routine->statement->parameters->parameters as $index => $parameter) {
            $target = $targets[$index] ?? null;
            $bound[] = match (true) {
                $parameter->mode === ParameterMode::Out => [null, Domain::null()],
                $target instanceof Variable => [$target->value, $target->domain],
                is_string($target) => $session->variables->user($target),
                default => $this->evaluated($planner->compiler->compile($statement->arguments[$index], new Scope()), $context),
            };
        }

        return $bound;
    }

    /**
     * Evaluates a compiled argument.
     *
     * @return array{int|float|string|null, Domain}
     */
    public function evaluated(\MySqlMemory\Evaluation\Evaluable $argument, Context $context): array
    {
        return [$argument->evaluate(new Frame($context)), $argument->domain()];
    }

    /**
     * Gives each variable of an OUT or INOUT parameter the value of the parameter, in the order of the parameters.
     *
     * @param array<int, string|Variable> $targets
     *
     * @throws SqlError When a variable of the calling program refuses the value
     */
    public function write(array $targets, Row $parameters, Session $session, Context $context): void
    {
        foreach ($targets as $index => $target) {
            $parameter = $parameters->variables[$index];
            if ($target instanceof Variable) {
                $target->assign($parameter->value, $parameter->domain, $context);
            } else {
                $session->variables->assign($target, $parameter->value, $parameter->domain);
            }
        }
    }
}
