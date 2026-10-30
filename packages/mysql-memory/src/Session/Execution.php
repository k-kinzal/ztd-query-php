<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

use LogicException;
use MySqlMemory\Command\Command;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Problem\Stages;
use MySqlMemory\Session\Statement\Lifecycle;
use RuntimeException;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use SqlSemantics\Statement\AnalyzedStatement;
use SqlSemantics\Statement\Node;
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
     * Executes the published semantic statement without rendering or analyzing it.
     *
     * @param list<array{int|float|string|\MySqlMemory\Evaluation\ExactDecimal|null, \MySqlMemory\Typing\Domain}> $parameters Bound parameter values
     * @param bool $read Whether initial input conditions are emitted here
     * @param bool $contained Whether successful writes belong to the enclosing statement
     * @throws SqlError When SQL execution fails
     * @throws LogicException When a statement has no execution implementation
     * @throws RuntimeException When the execution session selects another MySQL release
     */
    public function statement(AnalyzedStatement $statement, array $parameters = [], bool $read = true, bool $contained = true): Reply
    {
        $node = $statement;
        return $this->perform($statement, (new \MySqlMemory\Command\Dispatcher())->command($node), $parameters, $read, $contained);
    }

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
     * @param list<array{int|float|string|\MySqlMemory\Evaluation\ExactDecimal|null, \MySqlMemory\Typing\Domain}> $parameters The values bound to the parameter markers
     * @param bool $read Whether the statement was read from the text the session runs
     * @param bool $contained Whether the statement is part of the statement around it
     *
     * @throws SqlError When the statement fails
     * @throws RuntimeException When the statement belongs to another MySQL release
     * @throws LogicException When the statement has no semantic execution implementation
     */
    public function perform(AnalyzedStatement $operation, Command $command, array $parameters = [], bool $read = true, bool $contained = true): Reply
    {
        if ($operation->profile()->grammar !== $this->session->settings()->release()) {
            throw new RuntimeException('The statement and execution session must select the same MySQL release.');
        }
        $node = $operation;
        $hints = (new \MySqlMemory\Hint\Hints())->apply($node, $this->session);
        $database = $this->session->variables->database;
        $previous = $this->session->preparation->resolution;
        $this->session->preparation->resolution = $this->session->resolution();
        try {
            return $this->run($operation, $command, $hints, $parameters, $read, $contained);
        } catch (SqlError $error) {
            throw (new Problem\Precision())->legacy($error, $this->session->settings()->release());
        } finally {
            (new \MySqlMemory\Command\Explain\ExplainCommand())->restoreDatabase($operation, $this->session, $database);
            $this->session->preparation->resolution = $previous;
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
     * @param list<array{int|float|string|\MySqlMemory\Evaluation\ExactDecimal|null, \MySqlMemory\Typing\Domain}> $parameters The values bound to the parameter markers
     * @param bool $read Whether the statement was read from the text the session runs
     * @param bool $contained Whether the statement is part of the statement around it
     *
     * @throws SqlError When the statement fails
     * @throws LogicException When the statement has no semantic execution implementation
     */
    public function run(AnalyzedStatement $operation, Command $command, \MySqlMemory\Hint\Application $hints, array $parameters = [], bool $read = true, bool $contained = true): Reply
    {
        $session = $this->session;
        $node = $operation;
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
        $this->area($operation, $command);
        $late = $operation instanceof MySqlStatement ? $this->reading($operation, $read) : [];
        (new Statement\Reading())->read($operation, $session, false);
        State\StatementCounters::evaluated($node, $session);
        if ($node instanceof \SqlSemantics\Platform\MySql\Statement\Server\Instance\Kill) {
            (new \MySqlMemory\Command\Admin\KillCommand())->process($node, $session);
        }
        (new \MySqlMemory\Command\Show\Inspection())->check($node, $session);
        (new Access\StatementAccess())->check($node, $session, $context);
        if ($operation instanceof MySqlStatement) {
            (new Access\DefaultDatabase())->referenced($operation, $session);
        }
        $hints->report($session);
        if ($operation instanceof MySqlStatement) {
            foreach ($session->replayed || $this->undeclared($operation) ? [] : $late as $warning) {
                $session->diagnostics->warning($warning instanceof \SqlSemantics\Platform\MySql\Statement\Notice\CodedWarning ? $warning->code() : 1105, $warning->message());
            }
            (new \MySqlMemory\System\Guard())->check($operation, $session, $operation->statementInformation());
            (new \MySqlMemory\Concurrency\Access())->check($operation, $session);
            try {
                (new Problems())->raise($operation, $session);
            } catch (SqlError $error) {
                $error = (new \MySqlMemory\Command\Replication\PurgeErrors())->prepared($error, $operation);
                throw (new Problem\Precision())->legacy(\MySqlMemory\Command\Admin\KillCommand::preparation((new Problem\ValueRows())->extended($error, $operation, $session->settings()->release()), $operation), $session->settings()->release());
            }
            if ($session->locks !== []) {
                (new \MySqlMemory\Command\Access\Locks())->check($operation, $session);
            }
            (new Problem\Reopened())->check($operation, $session, $operation->statementInformation());
        } else {
            throw new LogicException('No semantic preparation for ' . $operation::class);
        }

        return (new Lifecycle($session))->run($node, fn (): Reply => $command->execute($operation, $session, $context, new Connection($session->variables, $context, $session->user, $session->host, $session->id, $parameters, $session->program, WeakReference::create($session))), $contained);
    }

    /**
     * Reports initial conditions and returns the warnings deferred until access checks.
     *
     * A legacy table target needs a database before later definition conditions are
     * read. Stored-program execution and replay keep their existing notice policy.
     *
     * @return array<int, \SqlSemantics\Statement\Fact\Warning> Deferred notices with their original indexes
     * @throws SqlError When an initial condition rejects the statement
     */
    public function reading(MySqlStatement $operation, bool $read): array
    {
        $session = $this->session;
        if ($operation instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowParseTree) {
            throw (new Parse\DebugRequest())->refusal($operation, (new \MySqlMemory\Command\InternalCommand())->error($operation), $session);
        }
        (new \MySqlMemory\Command\Condition\SignalProblems())->check($operation, $session);
        (new Access\TableCreation())->database($operation, $session);
        (new Access\DefaultDatabase())->check($operation, $session);
        (new Access\LockNames())->check($operation, $session, $read);
        $late = array_filter($operation->statementInformation()->warnings, Stages::afterReading(...));
        if ($read && !$session->replayed) {
            $this->parsing($operation, array_diff_key($operation->statementInformation()->warnings, $late));
        }
        if ($operation instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges && $operation->condition() !== null) {
            throw (new Parse\Entry\PrivilegeInput())->level($operation, $session, !$read);
        }
        foreach ($session->replayed ? $operation->statementInformation()->warnings : [] as $warning) {
            if ($warning instanceof Deprecation && $warning->construct === \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::BinaryBitwise) {
                $session->diagnostics->warning($warning->code(), $warning->message());
            }
        }
        if (!$read) {
            (new \MySqlMemory\Program\Routine\InspectionNotices())->start($operation, $session);
            if ($operation instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateTable && $operation->hasExecutionCondition()) {
                (new \MySqlMemory\Program\Definition\IndexMetadata())->refresh($operation, $session);
                (new \MySqlMemory\Program\Definition\TemporaryStorage())->start($operation, $session);
            }
        }
        try {
            (new Problem\Reading())->read($operation, $session);
        } catch (SqlError $error) {
            throw (new Problem\Precision())->legacy($error, $session->settings()->release());
        }

        return $late;
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
    public function area(AnalyzedStatement $operation, Command $command): void
    {
        $session = $this->session;
        (new Lifecycle($session))->start();
        if (!$operation instanceof MySqlStatement) {
            if ($command->clearsDiagnostics() && $this->retains($operation, $command)) {
                $session->diagnostics->retain();
            } elseif ($command->clearsDiagnostics()) {
                $session->diagnostics->clear();
            }
            return;
        }
        if ((new Parse\DebugRequest())->preserves($operation)) {
            return;
        }
        $resetsOnParseFailure = $operation instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowErrors || ($operation instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowWarnings && $session->settings()->legacy()) || $operation instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\GetDiagnostics;
        $signalFailure = ($operation instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal || $operation instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal) && $operation->statementInformation()->diagnostics !== [];
        if ($signalFailure) {
            $session->diagnostics->clear();
        } elseif ($command->clearsDiagnostics() && $this->retains($operation, $command)) {
            $session->diagnostics->retain();
        } elseif ($command->clearsDiagnostics() || ($resetsOnParseFailure && ($this->undeclared($operation) || array_filter($operation->statementInformation()->diagnostics, Stages::parsed(...)) !== [] || array_filter($operation->statementInformation()->warnings, static fn ($warning): bool => $warning instanceof ParseFailure) !== []))) {
            $session->diagnostics->clear();
        }
    }

    /**
     * Records the conditions the server raises while it parses a statement, in order: the
     * problem of the hint comment after its first keyword, the warnings, the problems of its
     * other hint comments, the names after a leading dot once a warning that is not raised at the head of
     * the statement comes, and the error of each problem found while parsing, up to one that
     * stops the parse; the first of those errors then fails the statement. Modern
     * contextualization is not reached when grammar interpretation reported an error.
     * Syntax notices elsewhere in the statement still retain their written order.
     *
     * @param array<int, \SqlSemantics\Statement\Fact\Warning> $warnings The warnings raised while the statement is read
     *
     * @throws SqlError When a problem is found while the statement is parsed
     */
    public function parsing(MySqlStatement $operation, array $warnings): void
    {
        $session = $this->session;
        $warnings = Stages::grammar((new Parse\SpatialWarnings())->accepted($operation, $warnings), $session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651, $operation->statementInformation());
        $hints = array_filter($warnings, static fn ($warning): bool => $warning instanceof \SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintError);
        $warnings = array_diff_key($warnings, $hints);
        $failure = null;
        (new \MySqlMemory\Hint\Hints())->warnings($hints, true, $session);
        foreach ((new Parse\Entry\ProgramInput())->retained($operation, $warnings, $session) as $warning) {
            if ($warning instanceof Parse\Entry\InputNote) {
                $session->diagnostics->note($warning->code, $warning->message);
                continue;
            }
            if ($warning instanceof SqlError) {
                $session->diagnostics->error($warning->getCode(), $warning->getMessage());
                $failure ??= $warning;
                break;
            }
            if ($warning instanceof ParseFailure) {
                $error = (new Parse\LocatedErrors())->parsed($warning, $operation, $session);
                $session->diagnostics->error($error->getCode(), $error->getMessage());
                $failure ??= $error;
                if ($warning->aborts) {
                    break;
                }
                continue;
            }
            $session->diagnostics->warning($warning instanceof \SqlSemantics\Platform\MySql\Statement\Notice\CodedWarning ? $warning->code() : 1105, $warning->message());
            if ($warning instanceof Deprecation && $warning->aborts) {
                break;
            }
        }
        (new \MySqlMemory\Hint\Hints())->warnings($hints, false, $session);
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
     * SHOW statements, KILL, PURGE BINARY LOGS and UNLOCK TABLES (verified on a live 5.6.51 server).
     * Source: https://dev.mysql.com/doc/refman/5.6/en/show-warnings.html.
     */
    public function retains(Node $statement, Command $command): bool
    {
        if ($this->session->settings()->release() !== \SqlSemantics\Contract\GrammarRelease::MySql5651) {
            return false;
        }
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowProfiles) {
            return true;
        }
        $kept = [
            \MySqlMemory\Command\EmptyCommand::class,
            \MySqlMemory\Command\TransactionCommand::class, \MySqlMemory\Command\Transaction\SetTransactionCommand::class, \MySqlMemory\Command\Transaction\SavepointCommand::class,
            \MySqlMemory\Command\Transaction\XaCommand::class, \MySqlMemory\Command\DatabaseCommand::class, \MySqlMemory\Command\Definition\Database\AlterDatabaseCommand::class, \MySqlMemory\Command\Definition\Database\UpgradeDatabaseCommand::class, \MySqlMemory\Command\Account\CreateUserCommand::class,
            \MySqlMemory\Command\Account\DropUserCommand::class, \MySqlMemory\Command\Account\AlterUserCommand::class, \MySqlMemory\Command\Account\RenameUserCommand::class,
            \MySqlMemory\Command\Account\SetPasswordCommand::class, \MySqlMemory\Command\Account\ShowGrantsCommand::class, \MySqlMemory\Command\Admin\FlushCommand::class,
            \MySqlMemory\Command\Show\Server\HelpCommand::class, \MySqlMemory\Command\Prepared\PreparedCommand::class, \MySqlMemory\Command\Program\CallCommand::class,
            \MySqlMemory\Command\Program\RoutineCommand::class, \MySqlMemory\Command\Program\EventCommand::class, \MySqlMemory\Command\Program\ShowCreateProgramCommand::class,
            \MySqlMemory\Command\Show\ShowCreateDatabaseCommand::class, \MySqlMemory\Command\Show\Server\ShowPrivilegesCommand::class,
            \MySqlMemory\Command\Show\Server\ShowProcesslistCommand::class, \MySqlMemory\Command\Replication\ReplicationShowCommand::class,
            \MySqlMemory\Command\Replication\BinaryLogCommand::class, \MySqlMemory\Command\Replication\ReplicaCommand::class, \MySqlMemory\Command\Admin\ForeignServerCommand::class,
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

            return $level === null || in_array($level::class, [\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel::class, \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\DatabaseLevel::class, \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\CurrentDatabaseLevel::class], true);
        }

        return in_array($command::class, [\MySqlMemory\Command\Admin\KillCommand::class, \MySqlMemory\Command\QueryCommand::class, \MySqlMemory\Command\SetCommand::class, \MySqlMemory\Command\DoCommand::class, \MySqlMemory\Command\Explain\ExplainCommand::class], true)
            && (new \MySqlMemory\Evaluation\Compile\Walker())->find($statement, \SqlSemantics\Platform\MySql\Statement\Relation\TableReference::class) === []
            && (new \MySqlMemory\Evaluation\Compile\Walker())->find($statement, \SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable::class) === []
            && (new \MySqlMemory\Evaluation\Compile\Walker())->find($statement, \SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget::class) === [];
    }

    /**
     * Tells whether an INTO clause or a GET DIAGNOSTICS target of the statement names a variable no running stored program declares: the parser stops there (ER_SP_UNDECLARED_VAR), before it warns about INTO written before the locking clauses, and GET DIAGNOSTICS then fails as a new statement that empties the diagnostics area (verified on a live 8.4 server).
     */
    public function undeclared(MySqlStatement $operation): bool
    {
        try {
            (new Problem\Reading())->into($operation, $this->session);
            if ($operation instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowWarnings || $operation instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowErrors) {
                (new Problem\Reading())->limited($operation->limit, $this->session);
            }
        } catch (SqlError) {
            return true;
        }

        return false;
    }

    /**
     * Records the failure of a statement of the text the session runs, and answers the replies the text then ends with: those of the stored program statements that ran before it, then the error.
     *
     * Execution has already unwound its own journal; this boundary never aborts an
     * enclosing statement. ROW_COUNT() answers -1. The error is recorded unless it
     * was recorded already, followed by the errors it carries, and a rollback that could not
     * restore every row warns ER_WARNING_NOT_COMPLETE_ROLLBACK.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/information-functions.html#function_row-count.
     *
     * @return list<Reply|SqlError>
     */
    public function failed(SqlError $error): array
    {
        $session = $this->session;
        $session->variables->rowCount = -1;
        $answers = [...$session->running->replies, ...$session->running->takeEmbedded()];
        $session->running->replies = [];
        if ($session->running->partial !== null) {
            $answers[] = $session->running->partial;
        }
        $session->running->partial = null;
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
