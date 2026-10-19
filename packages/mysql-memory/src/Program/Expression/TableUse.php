<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Expression;

use MySqlMemory\Command\Definition\StorageOptions;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Session\Session;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable;
use SqlSemantics\Statement\Operation;
use WeakReference;

/**
 * Prepares or invokes a retained table definition using only its semantic fields.
 *
 * @visibility MySqlMemory
 */
final class TableUse
{
    /**
     * Resolves the header before the reached table-role condition.
     *
     * @throws SqlError When a header name or the incompatible table role is reached
     */
    public function raise(DeferredCreateTable $definition, Session $session, bool $invocation = true): \MySqlMemory\Result\Reply
    {
        if ($invocation) {
            \MySqlMemory\Session\State\StatementCounters::query($session);
            \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_create_table');
            $session->diagnostics->clear();
        }
        $this->header($definition, $session);
        if ((new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Creation\CreationRoles())->columnless($definition->elements, $definition->query)) {
            return (new \MySqlMemory\Program\Definition\ColumnlessUse())->execute($definition, $session);
        }
        if ((new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\LegacySubqueries())->columns($definition->elements)) {
            return (new GenerationUse())->execute($definition, $session);
        }
        if ((new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Creation\CreationRoles())->readProblem($definition->query) !== null) {
            return (new \MySqlMemory\Program\Definition\CreationQueryUse())->execute($definition, $session);
        }
        if ((new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Creation\PartitionKeyRole())->missing($definition->elements, $definition->partitioning, $definition->query)) {
            return $this->partition($definition, $session);
        }
        throw SchemaError::WrongObject->error($definition->name->schema->value ?? $session->variables->database, $definition->name->name->value, 'BASE TABLE');
    }

    /**
     * Binds the complete declaration before the ordinary partition preparation path.
     */
    public function partition(DeferredCreateTable $definition, Session $session, ?string $preparationDatabase = null): \MySqlMemory\Result\Reply
    {
        $saved = $session->variables->database;
        $session->variables->database = $preparationDatabase ?? $saved;
        try {
            $database = $definition->name->schema->value ?? $session->variables->database;
            $derivation = new \SqlSemantics\Construction\Derivation($session->semantics()->context($session->instance->dictionary->declarations(), true, $database === '' ? null : new SearchPath($database), $session->resolution()));
            $definition->deriveProgram($derivation, new \SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope($derivation->environment()));
            $facts = $derivation->facts();
            $table = $facts->relation($definition)->table;
            \SqlSemantics\Diagnostic\Check::invariant($table instanceof \SqlSemantics\Statement\Reference\Table\DeclaredTable, 'A complete creation instruction supplies a table declaration.');
            $context = new Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
            $connection = new \MySqlMemory\Evaluation\Compile\Connection($session->variables, $context, session: WeakReference::create($session));
            $prepared = new \MySqlMemory\Session\Resolution\TablePreparation($definition, $facts, $table->table);
            $reply = (new \MySqlMemory\Command\Definition\CreateTableCommand())->create($prepared, $session, $context, $connection);
            $session->variables->rowCount = $reply instanceof \MySqlMemory\Result\Completion ? $reply->affectedRows : -1;
            return $reply;
        } finally {
            $session->variables->database = $saved;
        }
    }

    /**
     * Resolves engine options without opening the destination or the creation query.
     */
    public function header(DeferredCreateTable $definition, Session $session): void
    {
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
        $storage = new StorageOptions();
        $storage->header($definition, $session, $context);
        $this->contextualize($definition, $session);
        $storage->engine($definition, $session->variables, $context);
        $storage->directories($definition, $session);
    }

    /**
     * Preserves partition contextualization before invocation-time definition roles.
     *
     * @throws SqlError When a partition expression reads forbidden session state
     */
    public function contextualize(DeferredCreateTable $definition, Session $session): void
    {
        if ($definition->partitioning === null) {
            return;
        }
        $derivation = new \SqlSemantics\Construction\Derivation($session->semantics()->context(session: $session->resolution()));
        $definition->partitioning->derivePartitioning($derivation, $derivation->environment());
        foreach ($derivation->facts()->warnings as $warning) {
            if ($warning instanceof \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure && $warning->problem instanceof \SqlSemantics\Platform\MySql\Statement\Partition\Problem\InvalidPartitionExpression) {
                throw new SqlError(\MySqlMemory\Error\Family\StatementError::ParseError, $warning->problem->message(), new \SqlSemantics\Construction\RejectedInput($warning->problem, 0));
            }
            if ($warning instanceof \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation && $warning->phase() === \SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Contextualization) {
                $session->diagnostics->warning($warning->code(), $warning->message());
            }
        }
        foreach ($derivation->facts()->diagnostics as $problem) {
            if (\MySqlMemory\Session\Problem\Stages::parsed($problem)) {
                throw (new \MySqlMemory\Session\Problem\Errors())->error($problem, $session);
            }
        }
    }

    /**
     * Prepares the retained source at PREPARE, before storing the later table-role condition.
     *
     * @throws SqlError When the header or the creation query cannot prepare
     */
    public function prepare(DeferredCreateTable $definition, Session $session): ?Operation
    {
        $this->header($definition, $session);
        $query = $definition->query;
        if ((new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Creation\PartitionKeyRole())->missing($definition->elements, $definition->partitioning, $query)) {
            return null;
        }
        if ((new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Creation\CreationRoles())->columnless($definition->elements, $query) || (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\LegacySubqueries())->columns($definition->elements)) {
            return null;
        }
        \SqlSemantics\Diagnostic\Check::invariant($query !== null, 'A deferred table creation retains its query.');
        if ($query->query instanceof \SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody) {
            (new SelectionUse())->raise($query->query->selection, $session, false);
        }
        if ((new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadColumns())->problem($query->query) !== null) {
            (new QueryUse())->raise(new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredQuery($query->query), $session, false);
        }
        $database = $session->variables->database;
        $context = $session->semantics()->context($session->instance->dictionary->declarations(), true, $database === '' ? null : new SearchPath($database), $session->resolution());
        $operation = new Operation($context, new \SqlSemantics\Platform\MySql\Statement\QueryRoot($query->query));
        (new \MySqlMemory\Session\Problems())->raise($operation, $session);
        return $operation;
    }
}
