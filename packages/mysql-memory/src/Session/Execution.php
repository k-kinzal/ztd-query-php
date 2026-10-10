<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

use MySqlMemory\Command\Command;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Problem\Stages;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Statement\Operation;
use WeakReference;

/**
 * Executes one analyzed statement of a session with its command: the statements the session reads from its text, and those of the stored programs it runs.
 *
 * The statement starts a new diagnostics area unless its command keeps it, raises the problems
 * SQL Semantics found in it, and runs inside the statement journal of the transaction.
 * ROW_COUNT() then answers the rows the statement affected, or -1 when it answered rows or
 * failed; a CALL answers those of its last statement.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-functions.html#function_row-count.
 *
 * @visibility MySqlMemory
 */
final class Execution
{
    /**
     * @param Session $session The session that runs the statement
     */
    public function __construct(public readonly Session $session)
    {
    }

    /**
     * Executes one analyzed statement with its command, under the session values its SET_VAR hints give.
     *
     * The optimizer hints of the statement are applied before it starts and the variables they
     * set get their values back when it ends, whether it succeeds or fails ({@see \MySqlMemory\Hint\Hints}).
     *
     * @param list<array{int|float|string|null, \MySqlMemory\Typing\Domain}> $parameters The values bound to the parameter markers
     * @param bool $read Whether the statement was read from the text the session runs
     * @param bool $contained Whether the statement is part of the statement around it
     *
     * @throws SqlError When the statement fails
     */
    public function perform(Operation $operation, Command $command, array $parameters = [], bool $read = true, bool $contained = true): Reply
    {
        $hints = (new \MySqlMemory\Hint\Hints())->apply($operation->statement, $this->session);
        try {
            return $this->run($operation, $command, $hints, $parameters, $read, $contained);
        } finally {
            $hints->restore($this->session);
        }
    }

    /**
     * Executes one analyzed statement with its command, its hints applied.
     *
     * A statement read from its text records the conditions the server raises while it parses
     * it; a statement of a stored program had them recorded when the program was created. A
     * statement that fails restores the rows it changed. A statement that succeeds is part of
     * the statement around it, if any, unless it stands on its own, as a statement of a
     * procedure CALL does.
     *
     * @param \MySqlMemory\Hint\Application $hints The hints of the statement, applied, whose warnings it raises
     * @param list<array{int|float|string|null, \MySqlMemory\Typing\Domain}> $parameters The values bound to the parameter markers
     * @param bool $read Whether the statement was read from the text the session runs
     * @param bool $contained Whether the statement is part of the statement around it
     *
     * @throws SqlError When the statement fails
     */
    public function run(Operation $operation, Command $command, \MySqlMemory\Hint\Application $hints, array $parameters = [], bool $read = true, bool $contained = true): Reply
    {
        $session = $this->session;
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
        $this->area($operation, $command);
        $late = array_filter($operation->facts->warnings, Stages::afterReading(...));
        if ($read) {
            $this->parsing($operation, array_diff_key($operation->facts->warnings, $late));
        }
        try {
            (new Problem\Reading())->read($operation, $session);
        } catch (SqlError $error) {
            throw (new Problem\Precision())->legacy($error, $session->settings()->release());
        }
        (new Access\PasswordAccess())->check($operation->statement, $session);
        $hints->report($session);
        foreach ($this->undeclared($operation) ? [] : $late as $warning) {
            $session->diagnostics->warning($warning instanceof Deprecation ? $warning->code() : 1105, $warning->message());
        }
        (new \MySqlMemory\System\Guard())->check($operation->statement, $session);
        (new \MySqlMemory\Concurrency\Access())->check($operation->statement, $session);
        try {
            (new Problems())->raise($operation, $session);
        } catch (SqlError $error) {
            throw (new Problem\Precision())->legacy(\MySqlMemory\Command\Admin\KillCommand::preparation((new Problem\ValueRows())->extended($error, $operation->statement, $session->settings()->release()), $operation->statement), $session->settings()->release());
        }
        if ($session->locks !== []) {
            (new \MySqlMemory\Command\Access\Locks())->check($operation->statement, $session);
        }
        (new Problem\Reopened())->check($operation->statement, $session);
        if ($session->program?->contained === true) {
            $session->running->check($operation->statement, $session->variables->database);
        }
        $session->transaction->statements->begin($contained);
        $session->instance->dictionary->temporaries = $session->temporaries;
        $session->running->using[] = $session->running->used($operation->statement, $session->variables->database);
        try {
            $reply = $command->execute($operation, $session, $context, new Connection($session->variables, $context, $session->user, $session->host, $session->id, $parameters, $session->program, WeakReference::create($session)));
        } catch (SqlError $error) {
            $session->transaction->statements->abort();
            throw $error;
        } finally {
            array_pop($session->running->using);
        }
        $session->transaction->statements->end($contained);
        $last = $reply instanceof \MySqlMemory\Result\Batch ? ($reply->replies[count($reply->replies) - 1] ?? null) : $reply;
        $session->variables->rowCount = $last instanceof Completion ? $last->affectedRows : -1;

        return $reply;
    }

    /**
     * Starts the diagnostics area and clears the previous statement's interruption and insert-id assignment.
     *
     * The area remembers the conditions of the statement before, for GET DIAGNOSTICS, and takes
     * max_error_count. A command that clears the diagnostics keeps them when the statement
     * retains them, and empties the area otherwise; so does a GET DIAGNOSTICS that names an
     * undeclared variable or has a problem found while it is parsed, such as an unknown system
     * variable (verified on live 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0 servers).
     */
    public function area(Operation $operation, Command $command): void
    {
        $session = $this->session;
        $session->variables->setByFunction = false;
        $session->interrupted = false;
        $session->diagnostics->previous = [$session->diagnostics->raised(), $session->diagnostics->errors()];
        $session->diagnostics->limit = $session->variables->count('max_error_count', 1024);
        if ($command->clearsDiagnostics() && $this->retains($operation->statement, $command)) {
            $session->diagnostics->retain();
        } elseif ($command->clearsDiagnostics() || ($operation->statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\GetDiagnostics && ($this->undeclared($operation) || array_filter($operation->facts->diagnostics, Stages::parsed(...)) !== []))) {
            $session->diagnostics->clear();
        }
    }

    /**
     * Records the conditions the server raises while it parses a statement, in order: the
     * problem of the hint comment after its first keyword, the warnings, the problems of its
     * other hint comments, the names after a leading dot once a warning that is not raised at the head of
     * the statement comes, and the error of each problem found while parsing, up to one that
     * stops the parse; the first of those errors then fails the statement.
     *
     * @param array<int, \SqlSemantics\Statement\Fact\Warning> $warnings The warnings raised while the statement is read
     *
     * @throws SqlError When a problem is found while the statement is parsed
     */
    public function parsing(Operation $operation, array $warnings): void
    {
        $session = $this->session;
        $dots = $session->dots;
        $failure = null;
        foreach ($session->hinted as [$message, $leading]) {
            if ($leading) {
                $session->diagnostics->warning(StatementError::ParseError, $message);
            }
        }
        foreach ($warnings as $warning) {
            if (!$warning instanceof Deprecation || !in_array($warning->construct, Syntax::HEAD, true)) {
                foreach ($dots as $construct) {
                    $session->diagnostics->warning($construct->code(), $construct->value);
                }
                $dots = [];
            }
            if ($warning instanceof ParseFailure) {
                $error = (new Problem\Precision())->legacy((new Parse\LocatedErrors())->error($warning->problem, $operation, $session) ?? (new CacheOptions())->placed($warning->problem, $operation->statement, $session) ?? (new Errors())->error($warning->problem, $session, 'field list', $operation->statement), $session->settings()->release());
                $session->diagnostics->error($error->getCode(), $error->getMessage());
                $failure ??= $error;
                if ($warning->aborts) {
                    break;
                }
                continue;
            }
            $session->diagnostics->warning($warning instanceof Deprecation ? $warning->code() : 1105, $warning->message());
        }
        foreach ($session->hinted as [$message, $leading]) {
            if (!$leading) {
                $session->diagnostics->warning(StatementError::ParseError, $message);
            }
        }
        foreach ($dots as $construct) {
            $session->diagnostics->warning($construct->code(), $construct->value);
        }
        if ($failure !== null) {
            throw new SqlError($failure->error, $failure->getMessage(), $failure, [], null, null, true);
        }
    }

    /**
     * Tells whether a statement keeps the diagnostics of the statement before it until it raises a condition: in MySQL 5.6, a statement that opens no table.
     *
     * A query, SET, DO or EXPLAIN that names no table keeps them, as do the statements that start
     * or end a transaction, set its characteristics or a savepoint, the XA statements, the
     * statements about databases and USE, the account statements, GRANT and REVOKE at the global
     * and database levels, FLUSH, HELP, PREPARE, EXECUTE and CALL (whose own statements then
     * decide), CREATE and DROP of a stored routine or an event, and the SHOW statements that read
     * no table of the information schema: SHOW CREATE DATABASE, SHOW CREATE PROCEDURE and
     * FUNCTION, SHOW GRANTS, SHOW PRIVILEGES, SHOW PROCESSLIST, SHOW ENGINE and the replication
     * SHOW statements, PURGE BINARY LOGS and UNLOCK TABLES (verified on a live 5.6.51 server).
     * Source: https://dev.mysql.com/doc/refman/5.6/en/show-warnings.html.
     */
    public function retains(\SqlSemantics\Statement\Node $statement, Command $command): bool
    {
        if ($this->session->settings()->release() !== \SqlSemantics\Contract\GrammarRelease::MySql5651) {
            return false;
        }
        $kept = [
            \MySqlMemory\Command\TransactionCommand::class, \MySqlMemory\Command\Transaction\SetTransactionCommand::class, \MySqlMemory\Command\Transaction\SavepointCommand::class,
            \MySqlMemory\Command\Transaction\XaCommand::class, \MySqlMemory\Command\DatabaseCommand::class, \MySqlMemory\Command\Definition\AlterDatabaseCommand::class, \MySqlMemory\Command\Account\CreateUserCommand::class,
            \MySqlMemory\Command\Account\DropUserCommand::class, \MySqlMemory\Command\Account\AlterUserCommand::class, \MySqlMemory\Command\Account\RenameUserCommand::class,
            \MySqlMemory\Command\Account\SetPasswordCommand::class, \MySqlMemory\Command\Account\ShowGrantsCommand::class, \MySqlMemory\Command\Admin\FlushCommand::class,
            \MySqlMemory\Command\Show\Server\HelpCommand::class, \MySqlMemory\Command\Prepared\PreparedCommand::class, \MySqlMemory\Command\Program\CallCommand::class,
            \MySqlMemory\Command\Program\RoutineCommand::class, \MySqlMemory\Command\Program\EventCommand::class, \MySqlMemory\Command\Program\ShowCreateProgramCommand::class,
            \MySqlMemory\Command\Show\ShowCreateDatabaseCommand::class, \MySqlMemory\Command\Show\Server\ShowPrivilegesCommand::class,
            \MySqlMemory\Command\Show\Server\ShowProcesslistCommand::class, \MySqlMemory\Command\Replication\ReplicationShowCommand::class,
            \MySqlMemory\Command\Replication\BinaryLogCommand::class,
        ];
        foreach ($kept as $class) {
            if ($command instanceof $class) {
                return true;
            }
        }
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Lock\UnlockTables) {
            return true;
        }
        if ($command instanceof \MySqlMemory\Command\Program\DropProgramCommand) {
            return !$statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\DropProgram || $statement->kind !== \SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind::Trigger;
        }
        if ($command instanceof \MySqlMemory\Command\Show\Server\ShowEnginesCommand) {
            return !$statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineCatalog;
        }
        if ($command instanceof \MySqlMemory\Command\Account\GrantCommand || $command instanceof \MySqlMemory\Command\Account\RevokeCommand) {
            $level = $statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokePrivileges ? $statement->level : null;

            return $level === null || $level instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel || $level instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\DatabaseLevel;
        }

        return ($command instanceof \MySqlMemory\Command\QueryCommand || $command instanceof \MySqlMemory\Command\SetCommand || $command instanceof \MySqlMemory\Command\DoCommand || $command instanceof \MySqlMemory\Command\Explain\ExplainCommand)
            && (new \MySqlMemory\Evaluation\Compile\Walker())->find($statement, \SqlSemantics\Platform\MySql\Statement\Relation\TableReference::class) === [];
    }

    /**
     * Tells whether an INTO clause or a GET DIAGNOSTICS target of the statement names a variable no running stored program declares: the parser stops there (ER_SP_UNDECLARED_VAR), before it warns about INTO written before the locking clauses, and GET DIAGNOSTICS then fails as a new statement that empties the diagnostics area (verified on a live 8.4 server).
     */
    public function undeclared(Operation $operation): bool
    {
        try {
            (new Problem\Reading())->into($operation->statement, $this->session);
        } catch (SqlError) {
            return true;
        }

        return false;
    }

    /**
     * Records the failure of a statement of the text the session runs, and answers the replies the text then ends with: those of the stored program statements that ran before it, then the error.
     *
     * The statement journal is aborted and ROW_COUNT() answers -1. The error is recorded unless it
     * was recorded already, followed by the errors it carries, and a rollback that could not
     * restore every row warns ER_WARNING_NOT_COMPLETE_ROLLBACK.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/information-functions.html#function_row-count.
     *
     * @return list<Reply|SqlError>
     */
    public function failed(SqlError $error): array
    {
        $session = $this->session;
        $session->transaction->statements->abort();
        $session->variables->rowCount = -1;
        $answers = $session->running->replies;
        $session->running->replies = [];
        if (!$error->recorded) {
            $session->diagnostics->error($error->getCode(), $error->getMessage(), $error->signalled);
        }
        foreach ($error->following as [$code, $message]) {
            $session->diagnostics->error($code, $message);
        }
        if ($session->transaction->unrestored) {
            $session->transaction->unrestored = false;
            $session->diagnostics->warning(\MySqlMemory\Error\Family\TransactionError::NotCompleteRollback, \MySqlMemory\Error\Family\TransactionError::NotCompleteRollback->message());
        }
        $answers[] = $error;

        return $answers;
    }
}
