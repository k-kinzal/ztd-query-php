<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Problem\Stages;
use MySqlMemory\Session\Session;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\TableProblems;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Definition\DeferredCreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\NoColumns;
use SqlSemantics\Statement\Reference\Table\MissingTable;

/**
 * Executes a CREATE TABLE whose written definition the server executes only to refuse.
 *
 * The server reports the conditions of such a definition in a fixed order (verified on
 * 8.4.7, 9.1.0, 5.7.44 and 5.6.51): the tables the query and the functional key parts read
 * are opened first (ER_NO_SUCH_TABLE), then the reading-time conditions of the types and
 * collations, then the storage engine; a missing default database follows; from 8.0 the
 * database must exist and an existing table ends the statement (ER_TABLE_EXISTS_ERROR, or a
 * note with IF NOT EXISTS), where 9.0 and later refuse an empty definition before looking at
 * the table; functional key expressions are checked after the table; only then the written
 * problem of the columns and keys is reported. MySQL 5.6 and 5.7 report the written problem
 * before the database and the table exist.
 *
 * The session's table locks are checked first, as for every definition.
 *
 * @visibility MySqlMemory
 */
final class RefusedDefinitionCommand
{
    /**
     * Runs the refused definition in the server's order and raises its first condition, or completes it when IF NOT EXISTS finds the table.
     *
     * @throws SqlError When a condition of the definition is reached
     */
    public function execute(DeferredCreateTable $definition, Session $session, ?string $database = null): Reply
    {
        $legacy = $session->settings()->legacy();
        $release = $session->settings()->release();
        $database ??= $session->variables->database;
        $problem = (new TableProblems())->problem($definition->elements, $definition->query, $release);
        if ($problem === null) {
            return (new \MySqlMemory\Session\Execution($session))->statement($this->operation($definition, $session));
        }
        \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_create_table');
        $session->diagnostics->clear();
        $creation = new \MySqlMemory\Session\Access\TableCreation();
        $creation->locks($definition, $session);
        foreach ($this->facts($definition, $session)->diagnostics as $diagnostic) {
            if ($diagnostic instanceof MissingTable || Stages::parsed($diagnostic) || Stages::characters($diagnostic, $legacy) || $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize || $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\TooBigPrecision || $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation) {
                throw (new Errors())->error($diagnostic, $session);
            }
        }
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
        $storage = new StorageOptions();
        $storage->header($definition, $session, $context, $database);
        $creation->validate($definition, $session);
        $storage->directories($definition, $session);
        (new Partition\PartitionNames())->required($definition->partitioning, $legacy);
        if ($legacy && $definition->partitioning instanceof \SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause) {
            Partition\PartitionDefinitions::early($definition->partitioning, $definition->temporaryWords > 0, $storage->engine($definition, $session->variables), true);
        }
        if (!$legacy) {
            $schemaName = $definition->name->schema->value ?? $database;
            $schema = $session->instance->dictionary->schema($schemaName);
            if ($schema === null) {
                throw QueryError::BadDatabase->error($schemaName);
            }
            $engine = $storage->engine($definition, $session->variables);
            foreach ($definition->options as $option) {
                if ($option instanceof \SqlSemantics\Platform\MySql\Statement\Table\Option\TablespaceOption) {
                    $storage->tablespace($option, $definition, $engine, $session);
                }
            }
            if ($definition->partitioning instanceof \SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause) {
                Partition\PartitionDefinitions::early($definition->partitioning, $definition->temporaryWords > 0, $engine, false);
            }
            if ($problem instanceof NoColumns && in_array($release, [GrammarRelease::MySql901, GrammarRelease::MySql910], true)) {
                throw (new Errors())->error($problem, $session);
            }
            $name = $definition->name->name->value;
            $exists = $definition->temporaryWords > 0 ? $session->temporaries->table($schemaName, $name) !== null : $schema->table($name) !== null || isset($schema->views[$name]);
            if ($exists) {
                if (!$definition->ifNotExists) {
                    throw SchemaError::TableExists->error($name);
                }
                $context->note(SchemaError::TableExists, $name);

                return new Completion(0, 0, $context->diagnostics->raised());
            }
            if ($problem instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\DuplicateColumn) {
                throw (new Errors())->error($problem, $session);
            }
            (new Constraint\SpatialColumns())->prepare($definition, $session);
            (new Constraint\IndexOptions())->prepare($definition, $session, $context);
            $this->functional($definition);
            Constraint\CheckDefinitions::forbidden($definition, false, release: $release);
        }
        if ($problem instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\SpatialKeyArity) {
            throw $this->spatial($definition, $session);
        }

        throw (new Errors())->error($problem, $session);
    }

    /**
     * Answers the error of a spatial key with several parts: an engine without spatial indexes
     * refuses the key itself (MEMORY, and InnoDB on 5.6), 5.6 reports wrong arguments, later
     * releases too many key parts (verified on 5.6.51, 5.7.44 and 8.4.7).
     */
    public function spatial(DeferredCreateTable $definition, Session $session): SqlError
    {
        $engine = (new StorageOptions())->engine($definition, $session->variables);
        $release = $session->settings()->release();
        if (in_array(strtoupper($engine), ['MEMORY', 'HEAP'], true) || ($release === GrammarRelease::MySql5651 && strcasecmp($engine, 'InnoDB') === 0)) {
            return SchemaError::SpatialIndexUnsupported->error();
        }

        return $release === GrammarRelease::MySql5651 ? \MySqlMemory\Error\Family\StatementError::WrongArguments->error('SPATIAL INDEX') : SchemaError::TooManyKeyParts->error(1);
    }

    /**
     * Checks the expressions of the functional key parts as the server does before it counts
     * primary keys and resolves ordinary key columns: a functional primary key, a row-valued or
     * forbidden expression, and the subqueries of the key.
     *
     * @throws SqlError When a functional key part is refused
     */
    public function functional(DeferredCreateTable $definition): void
    {
        foreach ($definition->elements as $element) {
            if (!$element instanceof \SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition) {
                continue;
            }
            foreach ($element->parts as $part) {
                if (!$part instanceof \SqlSemantics\Platform\MySql\Statement\Table\Key\ExpressionPart && !$part instanceof \SqlSemantics\Platform\MySql\Statement\Table\Key\ArrayPart && !$part instanceof \SqlSemantics\Platform\MySql\Statement\Table\Key\Deferred\ExpressionPart) {
                    continue;
                }
                (new Constraint\FunctionalKeys())->check($part, $element);
                (new Expression\ExpressionRules())->forbidden($part->expression, Expression\ExpressionRole::Functional, $element->name?->column->value ?? $element->constraint?->name?->column->value ?? 'functional_index');
            }
        }
    }

    /**
     * Derives the facts of the stored definition against the catalog of the session: the tables it reads and the conditions of its types.
     */
    public function facts(DeferredCreateTable $definition, Session $session): \SqlSemantics\Platform\MySql\Statement\StatementSemantics
    {
        $database = $session->variables->database;
        $context = $session->semantics()->context(\MySqlMemory\Session\Statement\Catalog::declarations($session), true, $database === '' ? null : new \SqlSemantics\Contract\SearchPath($database), $session->resolution());
        $copy = \SqlSemantics\Platform\MySql\Construction\Occurrences::fresh($definition);
        $derivation = new \SqlSemantics\Construction\Derivation($context);
        $copy->deriveProgram($derivation, new \SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope($derivation->environment()));

        return (new \SqlSemantics\Platform\MySql\Construction\StatementResults())->of($derivation);
    }

    /**
     * Binds the executable statement of a stored definition the release accepts.
     */
    public function operation(DeferredCreateTable $definition, Session $session): \SqlSemantics\Platform\MySql\Statement\Statement
    {
        $database = $session->variables->database;
        $context = $session->semantics()->context(\MySqlMemory\Session\Statement\Catalog::declarations($session), true, $database === '' ? null : new \SqlSemantics\Contract\SearchPath($database), $session->resolution());

        return (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($context, new CreateTable($definition->name, $definition->elements, $definition->options, $definition->partitioning, $definition->query, $definition->temporaryWords, $definition->ifNotExists, $definition->enclosed, optionsCommas: $definition->optionsCommas));
    }
}
