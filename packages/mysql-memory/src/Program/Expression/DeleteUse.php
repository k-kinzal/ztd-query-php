<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Expression;

use LogicException;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Session;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredDelete;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredMultipleDelete;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use WeakReference;

/**
 * Resolves a retained deletion before checking its target's writable role.
 * No source SQL or replacement statement is used to prepare the input relations.
 *
 * @visibility MySqlMemory
 */
final class DeleteUse
{
    /**
     * Obtains the standalone request's first preparation condition.
     */
    public function error(DeferredDelete|DeferredMultipleDelete $write, Session $session): SqlError
    {
        try {
            $this->raise($write, $session, false);
        } catch (SqlError $error) {
            return $error;
        }
    }

    /**
     * Resolves the complete target namespace, reads and predicate at invocation.
     *
     * @throws SqlError When a dependency or the retained target role is reached
     * @throws LogicException When the retained deletion loses its required condition
     */
    public function raise(DeferredDelete|DeferredMultipleDelete $write, Session $session, bool $invocation = true): never
    {
        if ($invocation) {
            \MySqlMemory\Session\State\StatementCounters::query($session);
            \MySqlMemory\Session\State\StatementCounters::command($session, $write instanceof DeferredDelete ? 'Com_delete' : 'Com_delete_multi');
            $session->diagnostics->clear();
        }
        $database = $session->variables->database;
        $targets = $write instanceof DeferredDelete ? [$write->table->name] : $write->targets;
        if ($write instanceof DeferredMultipleDelete && $database === '' && array_filter($targets, static fn ($target): bool => $target->schema === null) !== []) {
            throw QueryError::NoDatabase->error();
        }
        \MySqlMemory\Session\Resolution\Views::refreshAll($session->instance->dictionary, $session->settings());
        $context = $session->semantics()->context($session->instance->dictionary->declarations(), true, $database === '' ? null : new SearchPath($database), $session->resolution());
        $derivation = new Derivation($context);
        $write->deriveProgram($derivation, new ProgramScope($derivation->environment()));
        $facts = $derivation->facts();
        foreach ($facts->diagnostics as $problem) {
            if (\MySqlMemory\Session\Problem\Stages::parsed($problem) || $problem instanceof \SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable) {
                throw (new Errors())->error($problem, $session);
            }
        }
        foreach ($facts->diagnostics as $problem) {
            if ($problem instanceof MissingTable || $problem instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\UnpartitionedTable || $problem instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\UnknownPartition) {
                throw (new Errors())->error($problem, $session);
            }
        }
        $input = (new \MySqlMemory\Session\JsonTables())->first($write, $facts);
        if ($input !== null) {
            throw $input;
        }
        (new \MySqlMemory\Session\Problem\Sampling())->write($facts);
        $this->inputs($write, $facts, $session);
        foreach ($facts->diagnostics as $problem) {
            throw (new Errors())->error($problem, $session, statement: $write);
        }
        throw new LogicException('A retained deletion has a read-only target condition.');
    }
    /**
     * Prepares retained relation operands before the destination role is reported.
     *
     * @throws SqlError When a reached JSON document or join cannot bind
     */
    public function inputs(DeferredDelete|DeferredMultipleDelete $write, \SqlSemantics\Statement\Fact\Facts $facts, Session $session): void
    {
        if ($write instanceof DeferredDelete && (new \SqlSemantics\Platform\MySql\Rules\Dml\Read\Ordering())->problem($write->orderBy) === null) {
            return;
        }
        $context = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
        $connection = new \MySqlMemory\Evaluation\Compile\Connection($session->variables, $context, $session->user, $session->host, $session->id, program: $session->program, session: WeakReference::create($session));
        $planner = new \MySqlMemory\Plan\Planner($write, $facts, $session->settings(), $connection, $session->instance->dictionary);
        $scope = new \MySqlMemory\Evaluation\Scope();
        if ($write instanceof DeferredDelete) {
            $slots = $facts->relation($write->table)->shape->slots;
            $scope->place($write->table, $planner->relations->shaped($write->table, []), array_map(static fn ($slot): string => $slot->name->value ?? '', $slots));
            $operands = new \MySqlMemory\Program\Write\UpdateOperands();
            $operands->operand($write->where, $planner, $scope, 'where clause');
            $operands->ordering($write->orderBy, $planner, $scope);
            return;
        }
        foreach ($write->tables as $table) {
            $planner->relations->plan($table, $scope);
        }
    }
}
