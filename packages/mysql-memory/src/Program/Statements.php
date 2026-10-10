<?php

declare(strict_types=1);

namespace MySqlMemory\Program;

use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Plan\Views;
use MySqlMemory\Result\Batch;
use MySqlMemory\Result\Reply;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Problems;
use MySqlMemory\Session\Session;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Statement;
use WeakReference;

/**
 * Runs the SQL statements and evaluates the expressions of a running stored program.
 *
 * A statement of a program is resolved where it runs: against the tables the server has then,
 * in the database of the program, and with the parameters and local variables in scope, which
 * a name denotes before a column (MYSQL-PROGRAM-VARIABLE-LOOKUP-001). It then runs as any
 * statement does, starting a new diagnostics area. A query of a procedure answers a result
 * set; a stored function or a trigger cannot answer one (ER_SP_NO_RETSET). An expression of
 * the program, such as the condition of IF, starts a new diagnostics area too but leaves
 * ROW_COUNT() as it was; a value assigned to a variable is computed and stored as a value
 * written to a column of the variable's type, so that a warning is an error under a strict
 * sql_mode, named after the variable (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/local-variable-scope.html,
 * https://dev.mysql.com/doc/refman/8.4/en/stored-program-restrictions.html.
 *
 * @visibility MySqlMemory
 */
final class Statements
{
    /**
     * @param Session $session The session the program runs in
     * @param Activation $activation The running program
     */
    public function __construct(public readonly Session $session, public readonly Activation $activation)
    {
    }

    /**
     * Resolves a statement of the program where it runs.
     */
    public function operation(Statement $statement): Operation
    {
        $session = $this->session;
        Views::refreshAll($session->instance->dictionary, $session->settings());
        $database = $session->variables->database;
        $semantics = $session->semantics();

        return new Operation($semantics->context($session->instance->dictionary->declarations(), true, $database === '' ? null : new SearchPath($database), $session->resolution()), $statement);
    }

    /**
     * Runs an SQL statement of the program and keeps the result sets a procedure answers.
     *
     * @throws SqlError When the statement fails, or a stored function or trigger answers a result set
     */
    public function run(Statement $statement): Reply
    {
        \MySqlMemory\Session\State\StatementCounters::query($this->session);
        $operation = $this->operation($statement);
        $command = (new Dispatcher())->command($operation->statement);
        $reply = (new \MySqlMemory\Session\Execution($this->session))->perform($operation, $command, [], false, $this->activation->contained);
        $results = array_values(array_filter($reply instanceof Batch ? $reply->replies : [$reply], static fn (Reply $reply): bool => $reply instanceof ResultSet));
        if ($results !== [] && $this->activation->contained) {
            throw ProgramError::ResultSetFromProgram->error($this->activation->kind === 'TRIGGER' ? 'trigger' : 'function');
        }
        foreach ($results as $result) {
            if ($this->session->running->respond !== null) {
                ($this->session->running->respond)($result);
            } else {
                $this->activation->results[] = $result;
            }
        }

        return $reply;
    }

    /**
     * Evaluates an expression of the program; assigned to a variable, the value is stored into it.
     *
     * @param bool $assigned Whether the value is stored elsewhere, as the value RETURN answers is
     *
     * @return array{int|float|string|null, Domain} The value and its type
     *
     * @throws SqlError When the expression is refused or its value cannot be stored
     */
    public function value(Scalar $expression, ?Variable $target = null, bool $assigned = false): array
    {
        $session = $this->session;
        \MySqlMemory\Session\State\StatementCounters::query($session);
        $select = new Select([], [new SelectExpression($expression)]);
        $operation = $this->operation($select);
        $session->diagnostics->clear();
        (new Problems())->raise($operation, $session);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, microtime(true));
        $context->strict = ($target !== null || $assigned) && $context->modes->strict();
        $connection = new Connection($session->variables, $context, $session->user, $session->host, $session->id, [], $this->activation, WeakReference::create($session));
        $planner = new Planner($select, $operation->facts, $session->settings(), $connection, $session->instance->dictionary);
        $compiled = $planner->compiler->compile($expression, new Scope());
        $value = $compiled->evaluate(new Frame($context));
        $target?->assign($value, $compiled->domain(), $context);

        return [$value, $compiled->domain()];
    }

    /**
     * Tells whether a condition of the program is true: neither false nor NULL.
     *
     * @throws SqlError When the condition is refused
     */
    public function truth(Scalar $condition): bool
    {
        [$value, $domain] = $this->value($condition);

        return Convert::toBool($value, $domain, new Context($this->session->modes(), $this->session->diagnostics, $this->session->variables, microtime(true))) === true;
    }
}
