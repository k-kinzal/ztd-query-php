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
    public function error(DeferredUpdate $write, Session $session): SqlError
    {
        try {
            $this->raise($write, $session, false);
        } catch (SqlError $error) {
            return $error;
        }
    }

    /**
     * Opens the original inputs in the current state without replacing the statement.
     *
     * @throws SqlError When a dependency or retained input constraint is reached
     * @throws LogicException When the retained constraint loses its native error
     */
    public function raise(DeferredUpdate $write, Session $session, bool $invocation = true): never
    {
        if ($invocation) {
            \MySqlMemory\Session\State\StatementCounters::query($session);
            \MySqlMemory\Session\State\StatementCounters::command($session, count($write->tables) > 1 ? 'Com_update_multi' : 'Com_update');
            $session->diagnostics->clear();
        }
        $database = $session->variables->database;
        \MySqlMemory\Session\Resolution\Views::refreshAll($session->instance->dictionary, $session->settings());
        $context = $session->semantics()->context($session->instance->dictionary->declarations(), true, $database === '' ? null : new SearchPath($database), $session->resolution());
        $derivation = new Derivation($context);
        $write->deriveProgram($derivation, new ProgramScope($derivation->environment()));
        $facts = $derivation->facts();
        foreach ($facts->diagnostics as $problem) {
            if (\MySqlMemory\Session\Problem\Stages::parsed($problem)) {
                throw (new Errors())->error($problem, $session);
            }
        }
        (new \MySqlMemory\Session\Problem\Reading())->legacyWrites($write, $facts, $session);
        foreach ($facts->diagnostics as $problem) {
            if ($problem instanceof MissingTable) {
                throw (new Errors())->error($problem, $session);
            }
        }
        $input = (new \MySqlMemory\Session\JsonTables())->first($write, $facts);
        if ($input !== null) {
            throw $input;
        }
        foreach ($facts->diagnostics as $problem) {
            throw (new Errors())->error($problem, $session, statement: $write);
        }
        throw new LogicException('A retained UPDATE keeps its input, target or clause contradiction.');
    }
}
