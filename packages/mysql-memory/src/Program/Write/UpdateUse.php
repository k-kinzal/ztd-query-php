<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Write;

use LogicException;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Session;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredUpdate;
use SqlSemantics\Statement\Reference\Table\MissingTable;

/**
 * Prepares a complete retained UPDATE before reporting its fixed input, target or clause constraint.
 *
 * @visibility MySqlMemory
 */
final class UpdateUse
{
    /**
     * Obtains the standalone request's first preparation condition.
     */
    public function error(DeferredUpdate|\SqlSemantics\Platform\MySql\Statement\Dml\Problem\Update\ConflictingUpdate $write, Session $session): SqlError
    {
        try {
            $this->raise($write, $session, false);
        } catch (SqlError $error) {
            return (new \MySqlMemory\Session\Problem\Precision())->legacy($error, $session->settings()->release());
        }
    }

    /**
     * Opens the original inputs in the current state without replacing the statement.
     *
     * @param list<array{int|float|string|null, \MySqlMemory\Typing\Domain}> $parameters Bound marker values
     * @throws SqlError When a dependency or retained input constraint is reached
     * @throws LogicException When the retained constraint loses its native error
     */
    public function raise(DeferredUpdate|\SqlSemantics\Platform\MySql\Statement\Dml\Problem\Update\ConflictingUpdate $write, Session $session, bool $invocation = true, array $parameters = []): never
    {
        if ($invocation) {
            \MySqlMemory\Session\State\StatementCounters::query($session);
            \MySqlMemory\Session\State\StatementCounters::command($session, count($write->tables) > 1 ? 'Com_update_multi' : 'Com_update');
            $session->diagnostics->clear();
        }
        $this->check($write, $session, parameters: $parameters);
        throw new LogicException('A retained UPDATE keeps its input, target or clause contradiction.');
    }

    /**
     * Checks reached preparation phases while retaining deferred assignment values.
     *
     * @param list<array{int|float|string|null, \MySqlMemory\Typing\Domain}> $parameters Bound marker values
     * @throws SqlError When an input, destination or reached clause cannot bind
     */
    public function check(DeferredUpdate|\SqlSemantics\Platform\MySql\Statement\Dml\Problem\Update\ConflictingUpdate $write, Session $session, bool $prepared = false, array $parameters = []): void
    {
        $database = $session->variables->database;
        \MySqlMemory\Session\Resolution\Views::refreshAll($session->instance->dictionary, $session->settings());
        $context = $session->semantics()->context($session->instance->dictionary->declarations(), true, $database === '' ? null : new SearchPath($database), $session->resolution((new \MySqlMemory\Session\Statement\Binding())->parameters($session, $parameters)));
        $output = new \SqlSemantics\Rendering\Output(new \SqlSemantics\Platform\MySql\Rendering\Codec($session->settings()->release()));
        $write->render($output);
        $derivation = new Derivation($context, parameters: $output->parameters());
        if ($write instanceof DeferredUpdate) {
            $write->deriveProgram($derivation, new ProgramScope($derivation->environment()));
        } else {
            $write->derive($derivation);
        }
        $facts = $derivation->facts();
        foreach ($facts->diagnostics as $problem) {
            if (\MySqlMemory\Session\Problem\Stages::parsed($problem)) {
                throw (new Errors())->error($problem, $session);
            }
        }
        $this->names($write, $facts, $session);
        (new \MySqlMemory\Session\Problem\Reading())->legacyWrites($write, $facts, $session);
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
        if ($write instanceof DeferredUpdate) {
            if ((new UpdateOperands())->prepare($write, $facts, $session, $prepared, $parameters)) {
                return;
            }
        }
        foreach ($facts->diagnostics as $problem) {
            throw (new Errors())->error($problem, $session, statement: $write);
        }

    }
    /**
     * Resolves implicit input databases before reaching an exact alias collision.
     * Later inputs cannot replace a collision already reached in the list.
     *
     * @throws SqlError When a reached input has no database or repeats a fixed name
     */
    public function names(DeferredUpdate|\SqlSemantics\Platform\MySql\Statement\Dml\Problem\Update\ConflictingUpdate $write, \SqlSemantics\Statement\Fact\Facts $facts, Session $session): void
    {
        $prefix = [];
        foreach ((new \SqlSemantics\Platform\MySql\Rules\Dml\TargetReferences())->occurrences($write->tables) as $source) {
            if ($session->variables->database === '') {
                foreach ((new \MySqlMemory\Session\Access\RelationEntry())->nodes($source, $facts) as $node) {
                    if (($node instanceof \SqlSemantics\Platform\MySql\Statement\Relation\TableReference || $node instanceof \SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable) && $facts->covers($node)) {
                        $table = $facts->relation($node)->table;
                        if ($table instanceof MissingTable && $table->name->schema === null) {
                            throw \MySqlMemory\Error\Family\QueryError::NoDatabase->error();
                        }
                    }
                }
            }
            $prefix[] = $source;
            $problem = (new \SqlSemantics\Platform\MySql\Rules\Dml\Update\Structure())->names($prefix);
            if ($problem !== null) {
                throw (new Errors())->error($problem, $session);
            }
        }
    }

}
