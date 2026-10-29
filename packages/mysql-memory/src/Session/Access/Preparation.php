<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Access;

use LogicException;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Hint\Hints;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\Problem\Reading;
use MySqlMemory\Session\Problems;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use WeakReference;

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
     * @throws LogicException When a statement has no preparation implementation
     */
    public function check(\SqlSemantics\Statement\AnalyzedStatement $operation, Session $session): void
    {

        if ($operation instanceof \SqlSemantics\Platform\MySql\Statement\Server\Transaction\TransactionStatement || $operation instanceof \SqlSemantics\Platform\MySql\Statement\Account\RenameUser || $operation instanceof \SqlSemantics\Platform\MySql\Statement\Account\CreateRole || $operation instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantRoles) {
            (new \MySqlMemory\Session\Statement\Reading())->read($operation, $session);
            $this->protocol($operation);
            return;
        }
        if (!$operation instanceof MySqlStatement) {
            throw new LogicException('No preparation implementation for ' . $operation::class);
        }
        if ($operation instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateIndex || $operation instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable || $operation instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges || $operation instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokePrivileges) {
            (new DefaultDatabase())->check($operation, $session);
        }
        (new Execution($session))->parsing($operation, $operation->statementInformation()->warnings);
        if ($this->conditional($operation, $session)) {
            return;
        }
        $this->protocol($operation);
        $this->program($operation, $session);
        (new Reading())->read($operation, $session);
        (new \MySqlMemory\Command\Show\Inspection())->check($operation, $session, true);
        (new DefaultDatabase())->check($operation, $session);
        $this->definitions($operation, $session);
        if ($operation instanceof \SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall) {
            (new \MySqlMemory\Program\Routine\LoadNotices())->prepare($operation, $session);
        }
        (new DefaultDatabase())->referenced($operation, $session);
        $this->hints($operation, $session);
    }

    /**
     * Prepares complete requests whose intrinsic conditions have command-specific order.
     *
     * @return bool Whether the request owns its remaining preparation phases
     * @throws SqlError When a reached operand refuses preparation
     */
    public function conditional(\SqlSemantics\Statement\Statement $statement, Session $session): bool
    {
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Delete || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertSet || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain) {
            (new Problems())->operands($statement, $session);
        }
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Instance\Kill) {
            (new \MySqlMemory\Command\Admin\KillCommand())->prepare($statement, $session);
            return true;
        }
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateTable && $statement->hasExecutionCondition()) {
            (new \MySqlMemory\Program\Expression\TableUse())->prepare($statement, $session);
            return true;
        }
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Alter\AlterTable && $statement->hasExecutionCondition()) {
            (new \MySqlMemory\Program\Expression\AlterUse())->header($statement, $session);
            $this->alterParameters($statement, $session);
            return true;
        }
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateIndex && $statement->hasExecutionCondition()) {
            (new \MySqlMemory\Program\Definition\IndexUse())->header($statement, $session);
            $this->alterParameters($statement, $session);
            return true;
        }
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Update && $statement->hasExecutionCondition()) {
            (new \MySqlMemory\Program\Write\UpdateUse())->check($statement, $session, true);
            return true;
        }
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokePrivileges && $statement->hasExecutionCondition($session->settings()->release())) {
            (new \MySqlMemory\Command\Account\RevokeCommand())->prepare($statement, $session);
            return true;
        }
        return false;
    }

    /**
     * Resolves conditions under temporary hint values and restores them on every outcome.
     *
     * @throws SqlError When resolution or a hint condition refuses preparation
     */
    public function hints(MySqlStatement $operation, Session $session): void
    {
        $application = (new Hints())->apply($operation, $session, true);
        try {
            $application->report($session);
            (new Problems())->raise($operation, $session, true);
            $this->jsonNulls($operation, $session);
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
     * Refuses statements without a prepared-protocol form before runtime lookup.
     * This includes SQL PREPARE, EXECUTE and DEALLOCATE themselves, independently
     * of the connection's database, named statements and dynamic input values.
     *
     * @throws SqlError When the command cannot be prepared
     */
    public function protocol(\SqlSemantics\Statement\Statement $statement): void
    {
        if (in_array($statement::class, [\SqlSemantics\Platform\MySql\Statement\Account\CreateRole::class, \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaStart::class, \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaEnd::class, \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaPrepare::class, \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaCommit::class, \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaRollback::class, \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaRecover::class], true)) {
            throw \MySqlMemory\Error\Family\StatementError::UnsupportedPreparedStatement->error();
        }
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Prepare || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Execute || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Deallocate || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsTo || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\GetDiagnostics) {
            throw \MySqlMemory\Error\Family\StatementError::UnsupportedPreparedStatement->error();
        }
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection || $statement instanceof \SqlSemantics\Platform\MySql\Statement\EmptyStatement || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Explain\UseDatabase || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerOpen || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerScan || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexRead || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexSeek || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerClose) {
            throw \MySqlMemory\Error\Family\StatementError::UnsupportedPreparedStatement->error();
        }
    }

    /**
     * Compiles reached, statement-constant JSON null tests during MySQL 5.7 query
     * preparation. Their result is rebound at EXECUTE, but a bad current document
     * already prevents PREPARE. Modern constant WHERE predicates retain their
     * preparation result. No row or subquery is evaluated.
     *
     * @throws SqlError When a retained null test fails during preparation
     */
    public function jsonNulls(MySqlStatement $operation, Session $session): void
    {
        if (!$operation instanceof \SqlSemantics\Platform\MySql\Statement\QueryRoot) {
            return;
        }
        $context = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
        $connection = new \MySqlMemory\Evaluation\Compile\Connection($session->variables, $context, $session->user, $session->host, $session->id, session: WeakReference::create($session));
        $compiler = (new \MySqlMemory\Plan\Planner($operation, $operation->statementInformation(), $session->settings(), $connection, $session->instance->dictionary, $session->temporaries, \MySqlMemory\Session\Statement\Catalog::snapshot($session)))->compiler;
        $nodes = (new Problems())->reached($operation);
        (new \MySqlMemory\Evaluation\Compile\Preparation\Escapes())->check($compiler, $nodes);
        (new \MySqlMemory\Evaluation\Compile\Preparation\JsonNulls())->check($compiler, $nodes);
        (new \MySqlMemory\Evaluation\Compile\Preparation\Conditions())->check($compiler, $nodes);
    }

    /**
     * Applies the routine and trigger protocol restriction after their reading
     * conditions, before catalog existence and invocation checks. A valid stored
     * body may be defined through the text protocol but not COM_STMT_PREPARE.
     * Observed on MySQL 5.6, 5.7, 8.0, 8.4 and 9.1.
     *
     * @throws SqlError When a header, body or protocol condition refuses preparation
     */
    public function program(MySqlStatement $operation, Session $session): void
    {
        $statement = $operation;
        if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction && !$statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure && !$statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger) {
            return;
        }
        $programs = new \MySqlMemory\Command\Program\ProgramProblems();
        $programs->definer($statement, $session->settings()->release());
        (new DefaultDatabase())->check($statement, $session);
        if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger) {
            (new \MySqlMemory\Command\Program\RoutineCommand())->types($statement, $session, new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, microtime(true)));
        }
        (new Reading())->read($operation, $session);
        foreach ($operation->statementInformation()->diagnostics as $diagnostic) {
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
    public function definitions(MySqlStatement $operation, Session $session): void
    {
        $statement = $operation;
        $this->storage($statement, $session);
        $this->resources($statement);
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
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\DropRole || ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\DropProgram && !$dropEvent) || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\AlterRoutine || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Spatial\CreateSpatialReference || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Spatial\DropSpatialReference || ($event && !$modern)) {
            throw \MySqlMemory\Error\Family\StatementError::UnsupportedPreparedStatement->error();
        }
        if ($event && \SqlSemantics\Platform\MySql\Rules\Typing\Prepared\ParameterInference::infer($operation) !== []) {
            $error = \MySqlMemory\Error\Family\StatementError::DynamicParametersRequireDml;
            throw new SqlError($error, $error->message(), recorded: true);
        }
    }

    /**
     * Resource-group definitions have no binary preparation form. SET RESOURCE
     * GROUP remains preparable. Name-domain errors precede this protocol check.
     * Observed on MySQL 8.0.44, 8.4.7 and 9.1.0.
     *
     * @throws SqlError When a resource-group definition is prepared
     */
    public function resources(\SqlSemantics\Statement\Statement $statement): void
    {
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\CreateResourceGroup || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\AlterResourceGroup || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\DropResourceGroup) {
            throw \MySqlMemory\Error\Family\StatementError::UnsupportedPreparedStatement->error();
        }
    }

    /**
     * General tablespace creation, alteration and deletion have no prepared
     * protocol form. Reading conditions precede this check, while catalog
     * existence follows it. Legacy ENGINE name resolution precedes the protocol
     * restriction; modern engine selection follows it. Observed on MySQL 5.6–9.1.
     *
     * @throws SqlError When the storage command is unavailable through preparation
     */
    public function storage(\SqlSemantics\Statement\Statement $statement, Session $session): void
    {
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateTablespace || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespace || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Storage\DropTablespace) {
            if ($session->settings()->legacy()) {
                $context = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
                (new \MySqlMemory\Command\Admin\Storage\LegacySupport())->engine($statement->options, $session, $context);
            }
            throw \MySqlMemory\Error\Family\StatementError::UnsupportedPreparedStatement->error();
        }
    }

    /**
     * Enforces the MySQL 9 prepared ALTER/INDEX marker restriction after header reading.
     * Marker occurrences belong to the semantic declaration, independently of input text.
     * The protocol error does not enter SHOW WARNINGS. Observed on 9.0.1 and 9.1.0.
     *
     * @throws SqlError When the complete declaration contains a dynamic parameter
     */
    public function alterParameters(\SqlSemantics\Platform\MySql\Statement\Alter\AlterTable|\SqlSemantics\Platform\MySql\Statement\Table\CreateIndex $definition, Session $session): void
    {
        if (!in_array($session->settings()->release(), [\SqlSemantics\Contract\GrammarRelease::MySql901, \SqlSemantics\Contract\GrammarRelease::MySql910], true) || (new \MySqlMemory\Command\Prepared\GenerationParameters())->occurrences($definition) === []) {
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
    public function read(string $sql, Session $session): \SqlSemantics\Statement\AnalyzedStatement|\MySqlMemory\Command\Prepared\PendingTable
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
