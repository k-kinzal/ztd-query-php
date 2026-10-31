<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Access\Load;

use LogicException;
use MySqlMemory\Command\Access\LoadDataCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Problem\Stages;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Statement\Scalar;
use WeakReference;

/**
 * Prepares a complete LOAD request in its destination table's resolved scope.
 *
 * The input header keeps database, local-file and separator conditions ahead of
 * assignment binding. No SQL text or substitute scalar statement is needed.
 *
 * @visibility MySqlMemory
 */
final class Preparation
{
    /**
     * Prepares entry and assignment conditions using the published statement bindings.
     *
     * @throws SqlError When a retained entry or operand condition is reached
     */
    public function check(LoadTable $load, Session $session): never
    {
        $charset = $load->charset?->name;
        if ($charset !== null && \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::named($charset->value) === null) {
            throw (new Errors())->error(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset($charset->value), $session);
        }
        $entry = new LoadDataCommand();
        if ($session->settings()->legacy()) {
            $entry->separators($load);
        }
        $database = $session->variables->database;
        if ($database === '' && $load->table->name->schema === null) {
            throw \MySqlMemory\Error\Family\QueryError::NoDatabase->error();
        }
        $facts = $load->statementInformation();
        foreach ($facts->diagnostics as $diagnostic) {
            if (Stages::parsed($diagnostic)) {
                throw (new Errors())->error($diagnostic, $session);
            }
        }
        $entry->entry($load, $facts, $session);
        $evaluation = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
        $connection = new \MySqlMemory\Evaluation\Compile\Connection($session->variables, $evaluation, $session->user, $session->host, $session->id, program: $session->program, session: WeakReference::create($session));
        $planner = new \MySqlMemory\Plan\Planner($load, $facts, $session->settings(), $connection, $session->instance->dictionary, $session->temporaries, \MySqlMemory\Session\Statement\Catalog::snapshot($session));
        $this->prepare($load, $planner, $session);
    }

    /**
     * Prepares written columns and values with their original occurrence bindings.
     *
     * @throws SqlError When a column or operand cannot prepare
     * @throws LogicException If a retained refusal loses its assignment condition
     */
    public function prepare(LoadTable $load, \MySqlMemory\Plan\Planner $planner, Session $session): never
    {
        $table = $session->instance->dictionary->table($load->table->name->schema->value ?? $session->variables->database, $load->table->name->name->value, $session->temporaries);
        \SqlSemantics\Diagnostic\Check::invariant($table !== null, 'The LOAD destination was opened before assignment preparation.');
        $scope = new \MySqlMemory\Evaluation\Scope();
        $scope->place($load->table, array_map(static fn ($column) => $column->domain, $table->definition->columns), array_map(static fn ($column): string => $column->name, $table->definition->columns), $table->definition);
        $columns = new \MySqlMemory\Command\Write\Assignments($planner, $table);
        foreach ($load->columns ?? [] as $column) {
            if ($column instanceof ColumnUse) {
                $columns->position($column);
            }
        }
        foreach ($load->assignments as $assignment) {
            $columns->position($assignment->column);
            if ($assignment->value instanceof DefaultRequest) {
                continue;
            }
            if ($assignment->value instanceof Scalar) {
                $planner->compiler->compile($assignment->value, $scope);
            } else {
                (new \MySqlMemory\Program\Expression\RowUse())->prepare($assignment->value, $planner->compiler, $scope);
                throw (new \MySqlMemory\Program\Expression\RowUse())->error($assignment->value);
            }
        }
        if ((new \SqlSemantics\Platform\MySql\Rules\Dml\LoadOptions())->fixedVariables($load->format, $load->fields, $load->columns ?? [])) {
            throw \MySqlMemory\Error\Family\StatementError::FixedRowsToVariable->error();
        }
        throw new LogicException('A conditional LOAD has an intrinsic assignment condition.');
    }
}
