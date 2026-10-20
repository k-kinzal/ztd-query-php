<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem\Definition;

use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Session\Session;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\SpatialKeyArity;
use SqlSemantics\Statement\Fact\Facts;

/**
 * Reaches bound spatial-key arity conditions after dependency and engine preparation.
 *
 * Arity is derived by Semantics. The executor selects the matching occurrence
 * and resolves the release and storage capabilities that determine its SQL error.
 *
 * @visibility MySqlMemory
 */
final class IndexShapes
{
    /**
     * Reports the condition attached to this exact key declaration.
     *
     * @throws \MySqlMemory\Error\SqlError When the spatial declaration cannot be stored
     */
    public function definition(IndexDefinition $index, Facts $facts, GrammarRelease $release, string $engine): void
    {
        foreach ($facts->diagnostics as $problem) {
            if (!$problem instanceof SpatialKeyArity || $problem->owner !== $index->parts[0]) {
                continue;
            }
            if (in_array(strtoupper($engine), ['MEMORY', 'HEAP'], true) || ($release === GrammarRelease::MySql5651 && strcasecmp($engine, 'InnoDB') === 0)) {
                throw SchemaError::SpatialIndexUnsupported->error();
            }
            throw $release === GrammarRelease::MySql5651 ? StatementError::WrongArguments->error('SPATIAL INDEX') : SchemaError::TooManyKeyParts->error(1);
        }
    }

    /**
     * Prepares functional dependencies before ordered key names, arity and ordinary columns.
     *
     * @throws \MySqlMemory\Error\SqlError When a retained declaration condition is reached
     */
    public function prepare(\SqlSemantics\Platform\MySql\Statement\Table\CreateTable|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable $create, \MySqlMemory\Dictionary\TableDefinition $definition, Planner $planner): void
    {
        $facts = $planner->compiler->facts;
        if (array_filter($facts->diagnostics, static fn (\SqlSemantics\Statement\Fact\Diagnostic $problem): bool => $problem instanceof SpatialKeyArity) === []) {
            return;
        }
        $indexes = array_values(array_filter($create->elements, static fn ($element): bool => $element instanceof IndexDefinition));
        $session = $planner->compiler->connection->session();
        \SqlSemantics\Diagnostic\Check::invariant($session !== null, 'A retained index declaration is prepared in its invoking session.');
        (new FunctionalQueries())->added($indexes, $facts, $session);
        $names = new IndexNames();
        if ($planner->settings->legacy()) {
            $names->check($facts);
        }
        $columns = array_map(static fn (\MySqlMemory\Dictionary\ColumnDefinition $column): string => mb_strtolower($column->name), $definition->columns);
        $names->columns($indexes, $columns, $facts, $planner->settings->release(), $definition->engine);
    }

    /**
     * Opens an existing CREATE INDEX target before its retained shape condition.
     *
     * @throws \MySqlMemory\Error\SqlError When target lookup or the bound condition fails
     */
    public function request(\MySqlMemory\Session\Resolution\IndexPreparation $prepared, Session $session): void
    {
        $statement = $prepared->statement;
        if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\Index\DeferredCreateIndex) {
            return;
        }
        $table = (new \MySqlMemory\Command\Definition\AlterTableCommand())->table($session, $statement->table->schema->value ?? $session->variables->database, $statement->table->name->value);
        $index = new IndexDefinition($statement->kind, $statement->parts);
        $this->definition($index, $prepared->facts, $session->settings()->release(), $table->definition->engine);
    }
}
