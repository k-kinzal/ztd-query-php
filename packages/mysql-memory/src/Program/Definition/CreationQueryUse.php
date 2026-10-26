<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Definition;

use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Result\Completion;
use MySqlMemory\Session\Problems;
use MySqlMemory\Session\Resolution\QueryPreparation;
use MySqlMemory\Session\Session;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;

/**
 * Resolves a creation query after its sources and destination have opened.
 *
 * An existing destination can satisfy IF NOT EXISTS before column preparation;
 * source tables still open first. PREPARE has a separate query-preparation path.
 * No ordinary table declaration with a contradictory read shape is fabricated.
 * Observed through SQL on MySQL 5.7.44 and 8.4.7.
 *
 * @visibility MySqlMemory
 */
final class CreationQueryUse
{
    /**
     * Checks dependencies before the destination, then reaches the retained read constraint.
     *
     * @throws \MySqlMemory\Error\SqlError When a source, destination or query condition is reached
     */
    public function execute(CreateTable $definition, Session $session): Completion
    {
        if (!$definition->temporary()) {
            $session->transaction->commit();
        }
        $query = $this->bound($definition, $session);
        $problems = new Problems();
        $problems->opened($query, $session, $query->facts->diagnostics);
        $database = $definition->name->schema->value ?? $session->variables->database;
        $schema = (new ColumnlessUse())->schema($definition, $database, $session);
        $name = $definition->name->name->value;
        $exists = $definition->temporary() ? $session->temporaries->table($database, $name) !== null : $schema->table($name) !== null || isset($schema->views[$name]);
        if ($exists) {
            if (!$definition->ifNotExists) {
                throw SchemaError::TableExists->error($name);
            }
            $session->diagnostics->note(SchemaError::TableExists, SchemaError::TableExists->message($name));
            if ((new \MySqlMemory\Session\Access\TableCreation())->requested($definition)) {
                $session->transaction->creation->begin();
            }
            $session->variables->rowCount = 0;
            return new Completion(warnings: $session->diagnostics->raised());
        }
        $problems->raise($query, $session);
        $problem = (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Creation\CreationRoles())->readProblem($definition->query);
        if ($problem === null) {
            $prepared = (new \MySqlMemory\Program\Expression\TableUse())->bound($definition, $session);
            (new \MySqlMemory\Session\Problem\Definition\FunctionalQueries())->added((new \MySqlMemory\Evaluation\Compile\Walker())->find($definition, \SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition::class), $prepared->facts, $session);
            (new \MySqlMemory\Session\Problem\Definition\IndexNames())->creation($prepared, $session);
            (new ColumnlessUse())->columns($definition, $schema, $session);
            $problem = (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Creation\DeclarationConstraints())->problem($definition->elements, $definition->query);
        }
        Check::invariant($problem !== null, 'A retained creation query has a fixed read-shape constraint.');
        throw (new \MySqlMemory\Session\Problem\Errors())->error($problem, $session);
    }

    /**
     * Binds the retained source against the invocation's catalog without a source string or AST.
     */
    public function bound(CreateTable $definition, Session $session): QueryPreparation
    {
        $query = $definition->query;
        Check::invariant($query !== null, 'A deferred creation read retains its complete query.');
        \MySqlMemory\Session\Resolution\Views::refreshAll($session->instance->dictionary, $session->settings());
        $database = $session->variables->database;
        $context = $session->semantics()->context($session->instance->dictionary->declarations(), true, $database === '' ? null : new SearchPath($database), $session->resolution());
        $derivation = new Derivation($context);
        $derivation->query($query->query, $derivation->environment());
        return new QueryPreparation($query->query, $derivation->facts(), $context->profile);
    }
}
