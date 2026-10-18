<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Expression;

use LogicException;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Problem\Stages;
use MySqlMemory\Session\Session;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertQuery;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertRows;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Scalar;
use WeakReference;

/**
 * Prepares a complete retained write, opening its target before value dependencies.
 *
 * Only the semantic write and the current database state are consulted. Every
 * operand remains in its own row and column scope; no substitute query is made.
 *
 * @visibility MySqlMemory
 */
final class InsertUse
{
    /**
     * Returns the standalone input's preparation error.
     */
    public function error(DeferredInsertRows|DeferredInsertQuery $write, Session $session): SqlError
    {
        try {
            $this->raise($write, $session, false);
        } catch (SqlError $error) {
            return $error;
        }
    }

    /**
     * Binds the write head, all rows and duplicate-key assignments in one context.
     *
     * @throws SqlError When the reached write cannot prepare
     */
    public function raise(DeferredInsertRows|DeferredInsertQuery $write, Session $session, bool $invocation = true): never
    {
        if ($invocation) {
            \MySqlMemory\Session\State\StatementCounters::query($session);
            \MySqlMemory\Session\State\StatementCounters::command($session, $write->into->replace ? 'Com_replace' : 'Com_insert');
            $session->diagnostics->clear();
        }
        \MySqlMemory\Session\Resolution\Views::refreshAll($session->instance->dictionary, $session->settings());
        $database = $session->variables->database;
        $context = $session->semantics()->context($session->instance->dictionary->declarations(), true, $database === '' ? null : new SearchPath($database), $session->resolution());
        $derivation = new Derivation($context);
        $write->deriveProgram($derivation, new ProgramScope($derivation->environment()));
        $facts = $derivation->facts();
        foreach ($facts->diagnostics as $problem) {
            if (Stages::parsed($problem)) {
                throw (new Errors())->error($problem, $session);
            }
        }
        (new \MySqlMemory\Session\Access\DefaultDatabase())->calls($write, $session);
        foreach ($facts->diagnostics as $problem) {
            if ($problem instanceof MissingTable) {
                throw (new Errors())->error($problem, $session);
            }
        }
        foreach ($facts->diagnostics as $problem) {
            if ($write instanceof DeferredInsertRows && ($problem instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Problem\ValueCountMismatch || $problem instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Problem\DuplicateColumn)) {
                throw (new Errors())->error($problem, $session);
            }
        }
        $evaluation = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
        $connection = new \MySqlMemory\Evaluation\Compile\Connection($session->variables, $evaluation, $session->user, $session->host, $session->id, program: $session->program, session: WeakReference::create($session));
        $planner = new \MySqlMemory\Plan\Planner($write, $facts, $session->settings(), $connection, $session->instance->dictionary);
        $this->prepare($write, $planner, $session);
    }

    /**
     * Compiles each complete row in the destination table's scope without writing it.
     *
     * @throws SqlError When an operand cannot prepare
     * @throws LogicException If a constructed template loses its required condition
     */
    public function prepare(DeferredInsertRows|DeferredInsertQuery $write, \MySqlMemory\Plan\Planner $planner, Session $session): never
    {
        $schema = $write->into->table->name->schema->value ?? $session->variables->database;
        $table = $session->instance->dictionary->table($schema, $write->into->table->name->name->value);
        \SqlSemantics\Diagnostic\Check::invariant($table !== null, 'The write target was opened before value preparation.');
        if ($write instanceof DeferredInsertRows || !$session->settings()->legacy() || ($session->preparation->inputPrepared && $session->settings()->release() !== \SqlSemantics\Contract\GrammarRelease::MySql5651)) {
            foreach ($write->into->columns->columns ?? [] as $column) {
                if ($column instanceof \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse) {
                    (new \MySqlMemory\Command\Write\Assignments($planner, $table))->position($column);
                }
            }
        }
        if ($write instanceof DeferredInsertQuery) {
            (new QueryUse())->prepare($write->source, $planner->compiler, write: $session->preparation->inputPrepared ? null : $write);
            $planner->query($write->source, null);
            throw (new Errors())->error($write->problem(), $session);
        }
        $scope = new \MySqlMemory\Evaluation\Scope();
        $scope->place($write->into->table, array_map(static fn ($column) => $column->domain, $table->definition->columns), array_map(static fn ($column): string => $column->name, $table->definition->columns), $table->definition);
        foreach ($write->rows as $row) {
            foreach ($row->values as $value) {
                if ($value instanceof DefaultRequest) {
                    continue;
                }
                if ($value instanceof Scalar) {
                    $planner->compiler->compile($value, $scope);
                } else {
                    (new RowUse())->prepare($value, $planner->compiler, $scope);
                    throw (new RowUse())->error($value);
                }
            }
        }
        throw new LogicException('A deferred write retains a non-scalar value condition.');
    }
}
