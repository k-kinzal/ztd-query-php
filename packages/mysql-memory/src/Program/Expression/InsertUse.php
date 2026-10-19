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
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertSet;
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
    public function error(DeferredInsertRows|DeferredInsertSet|DeferredInsertQuery $write, Session $session): SqlError
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
    public function raise(DeferredInsertRows|DeferredInsertSet|DeferredInsertQuery $write, Session $session, bool $invocation = true): never
    {
        if ($invocation) {
            \MySqlMemory\Session\State\StatementCounters::query($session);
            \MySqlMemory\Session\State\StatementCounters::command($session, $write->into->replace ? 'Com_replace' : 'Com_insert');
            $session->diagnostics->clear();
        }
        \MySqlMemory\Session\Resolution\Views::refreshAll($session->instance->dictionary, $session->settings());
        $database = $session->variables->database;
        $context = $session->semantics()->context($session->instance->dictionary->declarations(), true, $database === '' ? null : new SearchPath($database), $session->resolution());
        if ($write instanceof DeferredInsertSet && $database === '' && $write->into->table->name->schema === null) {
            throw \MySqlMemory\Error\Family\QueryError::NoDatabase->error();
        }
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
    public function prepare(DeferredInsertRows|DeferredInsertSet|DeferredInsertQuery $write, \MySqlMemory\Plan\Planner $planner, Session $session): never
    {
        $schema = $write->into->table->name->schema->value ?? $session->variables->database;
        $table = $session->instance->dictionary->table($schema, $write->into->table->name->name->value);
        \SqlSemantics\Diagnostic\Check::invariant($table !== null, 'The write target was opened before value preparation.');
        if ($write instanceof DeferredInsertRows) {
            $this->rows($write, $planner, $session, $table);
        }
        if ($write instanceof DeferredInsertSet) {
            $this->set($write, $planner, $table, $session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651);
        }
        if (!$session->settings()->legacy() || ($session->preparation->inputPrepared && $session->settings()->release() !== \SqlSemantics\Contract\GrammarRelease::MySql5651)) {
            foreach ($write->into->columns->columns ?? [] as $column) {
                if ($column instanceof \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse) {
                    (new \MySqlMemory\Command\Write\Assignments($planner, $table))->position($column);
                }
            }
        }
        (new QueryUse())->prepare($write->source, $planner->compiler, write: $session->preparation->inputPrepared ? null : $write);
        $planner->query($write->source, null);
        throw (new Errors())->error($write->problem(), $session);
    }

    /**
     * Prepares all SET destinations before their values and duplicate-key operands.
     *
     * @throws SqlError When a retained assignment cannot prepare
     * @throws LogicException If the template's required condition is absent
     */
    public function set(DeferredInsertSet $write, \MySqlMemory\Plan\Planner $planner, \MySqlMemory\Dictionary\StoredTable $table, bool $valuesFirst = false): never
    {
        $scope = new \MySqlMemory\Evaluation\Scope();
        $scope->place($write->into->table, array_map(static fn ($column) => $column->domain, $table->definition->columns), array_map(static fn ($column): string => $column->name, $table->definition->columns), $table->definition);
        if (!$valuesFirst) {
            foreach ($write->assignments as $assignment) {
                (new \MySqlMemory\Command\Write\Assignments($planner, $table))->position($assignment->column);
            }
        }
        foreach ($write->assignments as $assignment) {
            if ($assignment instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment) {
                (new RowUse())->prepare($assignment->value, $planner->compiler, $scope);
                throw (new RowUse())->error($assignment->value);
            }
            if ($assignment->value instanceof Scalar) {
                $planner->compiler->compile($assignment->value, $scope);
            }
        }
        if ($valuesFirst) {
            foreach ($write->assignments as $assignment) {
                (new \MySqlMemory\Command\Write\Assignments($planner, $table))->position($assignment->column);
            }
        }
        $this->updates($write, $planner, $scope, $table);
        throw new LogicException('A retained SET write has a non-scalar assignment.');
    }

    /**
     * Binds complete rows in order, retaining legacy first-row and following-error behavior.
     *
     * @throws SqlError When a destination, width or value cannot prepare
     * @throws LogicException When a retained write loses its required condition
     */
    public function rows(DeferredInsertRows $write, \MySqlMemory\Plan\Planner $planner, Session $session, \MySqlMemory\Dictionary\StoredTable $table): never
    {
        $scope = new \MySqlMemory\Evaluation\Scope();
        $scope->place($write->into->table, array_map(static fn ($column) => $column->domain, $table->definition->columns), array_map(static fn ($column): string => $column->name, $table->definition->columns), $table->definition);
        $oldest = $session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651;
        try {
            $this->head($write, $planner, $session, $scope, $table);
        } catch (SqlError $error) {
            throw (new \MySqlMemory\Session\Problem\ValueRows())->extended($error, $write, $session->settings()->release());
        }
        if ($oldest) {
            $this->updates($write, $planner, $scope, $table);
        }
        try {
            foreach (array_slice($write->rows, 1) as $index => $row) {
                $this->width($write, $index + 1, $table);
                $this->values($row, $planner, $scope);
            }
        } catch (SqlError $error) {
            throw (new \MySqlMemory\Session\Problem\ValueRows())->extended($error, $write, $session->settings()->release());
        }
        if (!$oldest) {
            $this->updates($write, $planner, $scope, $table);
        }
        throw new LogicException('A retained write has a width or non-scalar value condition.');
    }

    /**
     * Prepares first-row operands and written columns in the release's observed order.
     */
    public function head(DeferredInsertRows $write, \MySqlMemory\Plan\Planner $planner, Session $session, \MySqlMemory\Evaluation\Scope $scope, \MySqlMemory\Dictionary\StoredTable $table): void
    {
        $oldest = $session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651;
        if ($oldest) {
            $this->values($write->rows[0], $planner, $scope);
        }
        if ($session->settings()->legacy()) {
            $this->width($write, 0, $table);
        }
        foreach ($write->into->columns->columns ?? [] as $column) {
            if ($column instanceof \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse) {
                (new \MySqlMemory\Command\Write\Assignments($planner, $table))->position($column);
            }
        }
        foreach ($planner->compiler->facts->diagnostics as $problem) {
            if ($problem instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Problem\DuplicateColumn) {
                throw (new Errors())->error($problem, $session);
            }
        }
        $this->width($write, 0, $table);
        if (!$oldest) {
            $this->values($write->rows[0], $planner, $scope);
        }
    }

    /**
     * Binds every duplicate-key target and value after the preceding write dependencies.
     */
    public function updates(DeferredInsertRows|DeferredInsertSet $write, \MySqlMemory\Plan\Planner $planner, \MySqlMemory\Evaluation\Scope $scope, \MySqlMemory\Dictionary\StoredTable $table): void
    {
        $scope = clone $scope;
        $scope->inserted = $table->definition;
        if ($write->alias !== null) {
            $scope->place($write->alias, array_map(static fn ($column) => $column->domain, $table->definition->columns), array_map(static fn ($column): string => $column->name, $table->definition->columns), $table->definition);
        }
        foreach ($write->onDuplicate as $assignment) {
            (new \MySqlMemory\Command\Write\Assignments($planner, $table))->position($assignment->column);
            if ($assignment instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment) {
                (new RowUse())->prepare($assignment->value, $planner->compiler, $scope);
                throw (new RowUse())->error($assignment->value);
            }
            if ($assignment->value instanceof Scalar) {
                $planner->compiler->compile($assignment->value, $scope);
            }
        }
    }

    /**
     * Checks the reached row against destination columns and the first written row.
     *
     * @throws SqlError When the row has another width
     */
    public function width(DeferredInsertRows $write, int $index, \MySqlMemory\Dictionary\StoredTable $table): void
    {
        $count = count($write->rows[$index]->values);
        $listed = count($write->into->columns->columns ?? []);
        $width = $listed === 0 ? count(array_filter($table->definition->columns, static fn ($column): bool => !$column->invisible)) : $listed;
        if (($index > 0 && $count !== count($write->rows[0]->values)) || (($count !== 0 || $listed !== 0) && $count !== $width)) {
            throw \MySqlMemory\Error\Family\QueryError::WrongValueCountOnRow->error($index + 1);
        }
    }

    /**
     * Binds one row's operands in its destination scope, without evaluating or writing them.
     */
    public function values(\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertedRow|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertedRow $row, \MySqlMemory\Plan\Planner $planner, \MySqlMemory\Evaluation\Scope $scope): void
    {
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
}
