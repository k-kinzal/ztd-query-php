<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Expression;

use MySqlMemory\Command\Definition\StorageOptions;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Session\Session;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use WeakReference;

/**
 * Prepares or invokes a retained table definition using only its semantic fields.
 *
 * @visibility MySqlMemory
 */
final class TableUse
{
    /**
     * @param \MySqlMemory\Command\Definition\Expression\GenerationRewrite $rewrite Bound generation operands for this execution
     */
    public function __construct(public readonly \MySqlMemory\Command\Definition\Expression\GenerationRewrite $rewrite = new \MySqlMemory\Command\Definition\Expression\GenerationRewrite())
    {
    }

    /**
     * Resolves the header before the reached table-role condition.
     *
     * @throws SqlError When a header name or the incompatible table role is reached
     */
    public function raise(CreateTable $definition, Session $session): \MySqlMemory\Result\Reply
    {
        if (!$session->replayed) {
            $this->header($definition, $session);
        }
        (new \MySqlMemory\Session\Access\TableCreation())->locks($definition, $session);
        if ((new \SqlSemantics\Platform\MySql\Rules\Partition\MethodRoles())->deferred($definition->partitioning)) {
            return $this->partition($definition, $session);
        }
        if ((new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Creation\CreationRoles())->temporaryStorage($definition->temporary(), $definition->options)) {
            (new \MySqlMemory\Program\Definition\TemporaryStorage())->raise($definition, $session);
        }
        if ((new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\LegacySubqueries())->columns($definition->elements)) {
            return (new GenerationUse($this->rewrite))->execute($definition, $session);
        }
        if ((new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Creation\CreationRoles())->readProblem($definition->query) !== null) {
            return (new \MySqlMemory\Program\Definition\CreationQueryUse())->execute($definition, $session);
        }
        if ((new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\Roles())->elements($definition->elements) || (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Creation\DeclarationConstraints())->problem($definition->elements, $definition->query) !== null || (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Creation\PartitionKeyRole())->missing($definition->elements, $definition->partitioning, $definition->query)) {
            return $this->partition($definition, $session);
        }
        throw SchemaError::WrongObject->error($definition->name->schema->value ?? $session->variables->database, $definition->name->name->value, 'BASE TABLE');
    }

    /**
     * Binds the complete declaration before column, key and partition preparation.
     */
    public function partition(CreateTable $definition, Session $session, ?string $preparationDatabase = null): \MySqlMemory\Result\Reply
    {
        $saved = $session->variables->database;
        $session->variables->database = $preparationDatabase ?? $saved;
        try {
            if ((new \SqlSemantics\Platform\MySql\Rules\Partition\MethodRoles())->deferred($definition->partitioning)) {
                $database = $definition->name->schema->value ?? $session->variables->database;
                if (!$session->settings()->legacy() && $session->instance->dictionary->schema($database) === null) {
                    throw \MySqlMemory\Error\Family\QueryError::BadDatabase->error($database);
                }
                throw \MySqlMemory\Error\Family\PartitionError::FunctionNotAllowed->error();
            }
            if ($definition->query !== null) {
                return (new \MySqlMemory\Program\Definition\CreationQueryUse())->execute($definition, $session);
            }
            $prepared = $this->bound($definition, $session);
            (new \MySqlMemory\Session\Problem\Definition\FunctionalQueries())->prepare($prepared, $session, new \MySqlMemory\Session\Problems());
            $context = new Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
            $connection = new \MySqlMemory\Evaluation\Compile\Connection($session->variables, $context, session: WeakReference::create($session));
            (new \MySqlMemory\Command\Definition\Constraint\SpatialColumns())->prepare($definition, $session);
            (new \MySqlMemory\Command\Definition\Constraint\IndexOptions())->prepare($definition, $session, $context);
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
    public function header(CreateTable $definition, Session $session, bool $preparing = false): void
    {
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
        $storage = new StorageOptions();
        $storage->header($definition, $session, $context);
        $this->contextualize($definition, $session);
        (new \MySqlMemory\Command\Definition\Expression\GenerationExecution())->variables($definition, $session);
        $storage->engine($definition, $session->variables, $context);
        (new \MySqlMemory\Command\Prepared\GenerationParameters())->defaults($definition);
        if ($preparing && (new \MySqlMemory\Session\Access\TableCreation())->requested($definition)) {
            throw \MySqlMemory\Error\Family\StatementError::UnsupportedPreparedStatement->error();
        }
        (new \MySqlMemory\Session\Access\TableCreation())->validate($definition, $session);
        $storage->directories($definition, $session);
    }

    /**
     * Preserves partition contextualization before invocation-time definition roles.
     *
     * @throws SqlError When a partition expression reads forbidden session state
     */
    public function contextualize(CreateTable $definition, Session $session): void
    {
        if ($definition->partitioning === null) {
            return;
        }
        $derivation = new \SqlSemantics\Construction\Derivation($session->semantics()->context(session: $session->resolution()));
        \SqlSemantics\Platform\MySql\Construction\Occurrences::fresh($definition->partitioning)->derivePartitioning($derivation, $derivation->environment());
        foreach ((new \SqlSemantics\Platform\MySql\Construction\StatementResults())->of($derivation)->warnings as $warning) {
            if ($warning instanceof \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure && $warning->problem instanceof \SqlSemantics\Platform\MySql\Statement\Partition\Problem\RefusedScalar && $warning->problem->condition === \SqlSemantics\Platform\MySql\Statement\Partition\Problem\OperandCondition::Subquery) {
                throw new SqlError(\MySqlMemory\Error\Family\StatementError::ParseError, $warning->problem->message(), new \SqlSemantics\Construction\RejectedInput($warning->problem, 0));
            }
            if ($warning instanceof \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure && $warning->problem instanceof \SqlSemantics\Platform\MySql\Statement\Partition\Problem\InvalidPartitionExpression) {
                throw new SqlError(\MySqlMemory\Error\Family\StatementError::ParseError, $warning->problem->message(), new \SqlSemantics\Construction\RejectedInput($warning->problem, 0));
            }
            if ($warning instanceof \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation && $warning->phase() === \SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Contextualization) {
                $session->diagnostics->warning($warning->code(), $warning->message());
            }
        }
        foreach ((new \SqlSemantics\Platform\MySql\Construction\StatementResults())->of($derivation)->diagnostics as $problem) {
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
    public function prepare(CreateTable $definition, Session $session): ?MySqlStatement
    {
        $this->header($definition, $session, true);
        (new \MySqlMemory\Session\Access\TableCreation())->locks($definition, $session);
        $query = $definition->query;
        if ((new \SqlSemantics\Platform\MySql\Rules\Partition\MethodRoles())->deferred($definition->partitioning)) {
            return null;
        }
        if ($query === null && (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Creation\CreationRoles())->temporaryStorage($definition->temporary(), $definition->options)) {
            return null;
        }
        if ($query === null && ((new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\Roles())->elements($definition->elements) || (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Creation\DeclarationConstraints())->problem($definition->elements, null) !== null)) {
            (new \MySqlMemory\Session\Problem\Definition\FunctionalQueries())->prepare($this->bound($definition, $session), $session, new \MySqlMemory\Session\Problems());
            return null;
        }
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
        $context = $session->semantics()->context(\MySqlMemory\Session\Statement\Catalog::declarations($session), true, $database === '' ? null : new SearchPath($database), $session->resolution());
        $operation = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($context, new \SqlSemantics\Platform\MySql\Statement\QueryRoot($query->query), new \SqlSemantics\Statement\Source\SourceMap());
        (new \MySqlMemory\Session\Problems())->raise($operation, $session);
        return $operation;
    }

    /**
     * Binds the complete request through the existing table-statement boundary.
     */
    public function bound(CreateTable $definition, Session $session): MySqlStatement
    {
        $database = $session->variables->database;
        return (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($session->semantics()->context(\MySqlMemory\Session\Statement\Catalog::declarations($session), true, $database === '' ? null : new SearchPath($database), $session->resolution()), $definition, new \SqlSemantics\Statement\Source\SourceMap());
    }
}
