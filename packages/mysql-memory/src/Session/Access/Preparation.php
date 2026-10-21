<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Access;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Hint\Hints;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\Problem\Reading;
use MySqlMemory\Session\Problems;
use MySqlMemory\Session\Session;
use SqlSemantics\Statement\Operation;

/**
 * Prepares an operation independently of its input transport. Resource-group
 * binding conditions follow name resolution, including a preparation failure;
 * SET_VAR and registration conditions precede it. Observed on MySQL 8.0–9.1.
 *
 * @visibility MySqlMemory
 */
final class Preparation
{
    /**
     * Resolves the operation while preserving native condition order and restoring
     * temporary SET_VAR values on both success and failure.
     *
     * @throws SqlError When preparation rejects the operation
     */
    public function check(Operation $operation, Session $session): void
    {
        if ($operation->statement instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateIndex) {
            (new DefaultDatabase())->check($operation->statement, $session);
        }
        (new Execution($session))->parsing($operation, $operation->facts->warnings);
        if (($operation->statement instanceof \SqlSemantics\Statement\Script && $operation->statement->statements === []) || $operation->statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Explain\UseDatabase || $operation->statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerOpen || $operation->statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerScan || $operation->statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexRead || $operation->statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexSeek || $operation->statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerClose) {
            throw \MySqlMemory\Error\Family\StatementError::UnsupportedPreparedStatement->error();
        }
        $this->program($operation, $session);
        (new Reading())->read($operation, $session, true);
        (new DefaultDatabase())->check($operation->statement, $session);
        $this->definitions($operation, $session);
        if ($operation->statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall) {
            (new \MySqlMemory\Program\Routine\LoadNotices())->prepare($operation->statement, $session);
        }
        (new DefaultDatabase())->referenced($operation, $session);
        $application = (new Hints())->apply($operation->statement, $session, true);
        try {
            $application->report($session);
            (new Problems())->raise($operation, $session, true);
        } catch (SqlError $error) {
            $error = (new \MySqlMemory\Session\Problem\Precision())->legacy($error, $session->settings()->release());
            if (!$error->recorded) {
                $session->diagnostics->error($error->getCode(), $error->getMessage(), $error->signalled);
            }
            foreach ($error->following as [$code, $message]) {
                $session->diagnostics->error($code, $message);
            }
            throw new SqlError($error->error, $error->getMessage(), $error, [], $error->signalled, $error->getCode(), true);
        } finally {
            $application->restore($session);
            foreach ($application->resourceWarnings as [$code, $message]) {
                $session->diagnostics->warning($code, $message);
            }
        }
    }

    /**
     * Applies the routine and trigger protocol restriction after their reading
     * conditions, before catalog existence and invocation checks. A valid stored
     * body may be defined through the text protocol but not COM_STMT_PREPARE.
     * Observed on MySQL 5.6, 5.7, 8.0, 8.4 and 9.1.
     *
     * @throws SqlError When a header, body or protocol condition refuses preparation
     */
    public function program(Operation $operation, Session $session): void
    {
        $statement = $operation->statement;
        if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction && !$statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure && !$statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger) {
            return;
        }
        $programs = new \MySqlMemory\Command\Program\ProgramProblems();
        $programs->definer($statement, $session->settings()->release());
        (new DefaultDatabase())->check($statement, $session);
        if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger) {
            (new \MySqlMemory\Command\Program\RoutineCommand())->types($statement, $session, new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, microtime(true)));
        }
        (new Reading())->read($operation, $session, true);
        foreach ($operation->facts->diagnostics as $diagnostic) {
            if ($programs->parsed($diagnostic, false)) {
                throw (new \MySqlMemory\Session\Problem\Errors())->error($diagnostic, $session, 'field list', $statement);
            }
        }
        throw \MySqlMemory\Error\Family\StatementError::UnsupportedPreparedStatement->error();
    }

    /**
     * Applies protocol restrictions after header reading and before schedule evaluation.
     *
     * @throws SqlError When the object command has no prepared protocol form
     */
    public function definitions(Operation $operation, Session $session): void
    {
        $statement = $operation->statement;
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateTable) {
            $storage = new \MySqlMemory\Command\Definition\StorageOptions();
            $context = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
            $storage->resolve($statement, $session, $context);
        }
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Alter\AlterTable || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateIndex) {
            $this->alterParameters($statement, $session);
        }
        $dropEvent = $statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\DropProgram && $statement->kind === \SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind::Event;
        $event = $statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateEvent || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\AlterEvent || $dropEvent;
        $modern = in_array($session->settings()->release(), [\SqlSemantics\Contract\GrammarRelease::MySql901, \SqlSemantics\Contract\GrammarRelease::MySql910], true);
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\CreateRole || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\DropRole || ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\DropProgram && !$dropEvent) || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\AlterRoutine || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Spatial\CreateSpatialReference || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Spatial\DropSpatialReference || ($event && !$modern)) {
            throw \MySqlMemory\Error\Family\StatementError::UnsupportedPreparedStatement->error();
        }
        if ($event && \SqlSemantics\Platform\MySql\Statement\Variable\ParameterInference::infer($operation) !== []) {
            $error = \MySqlMemory\Error\Family\StatementError::DynamicParametersRequireDml;
            throw new SqlError($error, $error->message(), recorded: true);
        }
    }

    /**
     * Enforces the MySQL 9 prepared ALTER/INDEX marker restriction after header reading.
     * Marker occurrences belong to the semantic declaration, independently of input text.
     * The protocol error does not enter SHOW WARNINGS. Observed on 9.0.1 and 9.1.0.
     *
     * @throws SqlError When the complete declaration contains a dynamic parameter
     */
    public function alterParameters(\SqlSemantics\Platform\MySql\Statement\Alter\AlterTable|\SqlSemantics\Platform\MySql\Statement\Table\CreateIndex|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredAlterTable|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\Index\DeferredCreateIndex $definition, Session $session): void
    {
        if (!in_array($session->settings()->release(), [\SqlSemantics\Contract\GrammarRelease::MySql901, \SqlSemantics\Contract\GrammarRelease::MySql910], true) || (new \MySqlMemory\Command\Prepared\GenerationParameters())->occurrences($definition, $session->settings()->release()) === []) {
            return;
        }
        $error = \MySqlMemory\Error\Family\StatementError::DynamicParametersRequireDml;
        throw new SqlError($error, $error->message(), recorded: true);
    }

    /**
     * Reads prepared input while retaining completed conditions before malformed syntax.
     *
     * @throws SqlError When splitting or semantic interpretation refuses the input
     */
    public function read(string $sql, Session $session): Operation|\MySqlMemory\Command\Prepared\PendingInstruction
    {
        (new \MySqlMemory\Session\Parse\Preparation\EmptyPrepared())->check($sql, $session->semantics()->parser(), $session->resolution()->client, $session->settings()->legacy());
        try {
            $statements = $session->split($sql);
        } catch (SqlError $error) {
            $previous = $session->text;
            try {
                $failure = (new \MySqlMemory\Session\Parse\Reader())->refused($error, $sql, true, $session);
                throw new SqlError($failure->error, $failure->getMessage(), $failure, [], $failure->signalled, $failure->getCode(), true);
            } finally {
                $session->text = $previous;
            }
        }
        if (count($statements) > 1) {
            $offset = (int) strpos($sql, ltrim($statements[1]), strlen($statements[0]));
            throw \MySqlMemory\Error\Family\StatementError::ParseError->error(mb_strcut(substr($sql, $offset), 0, 80, 'UTF-8'), substr_count(substr($sql, 0, $offset), "\n") + 1);
        }
        try {
            return $session->analyze($sql, true);
        } catch (\MySqlMemory\Session\Parse\Definition\DeferredPreparation $pending) {
            return $pending->definition;
        }
    }
}
