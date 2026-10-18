<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Expression;

use LogicException;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Session;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredMultipleDelete;
use SqlSemantics\Statement\Reference\Table\MissingTable;

/**
 * Resolves a retained deletion before checking its target's writable role.
 * No source SQL or replacement statement is used to prepare the input relations.
 *
 * @visibility MySqlMemory
 */
final class DeleteUse
{
    /**
     * Obtains the standalone request's first preparation condition.
     */
    public function error(DeferredMultipleDelete $write, Session $session): SqlError
    {
        try {
            $this->raise($write, $session, false);
        } catch (SqlError $error) {
            return $error;
        }
    }

    /**
     * Resolves the complete target namespace, reads and predicate at invocation.
     *
     * @throws SqlError When a dependency or the retained target role is reached
     * @throws LogicException When the retained deletion loses its required condition
     */
    public function raise(DeferredMultipleDelete $write, Session $session, bool $invocation = true): never
    {
        if ($invocation) {
            \MySqlMemory\Session\State\StatementCounters::query($session);
            \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_delete_multi');
            $session->diagnostics->clear();
        }
        $database = $session->variables->database;
        if ($database === '' && array_filter($write->targets, static fn ($target): bool => $target->schema === null) !== []) {
            throw QueryError::NoDatabase->error();
        }
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
        foreach ($facts->diagnostics as $problem) {
            if ($problem instanceof MissingTable) {
                throw (new Errors())->error($problem, $session);
            }
        }
        foreach ($facts->diagnostics as $problem) {
            throw (new Errors())->error($problem, $session, statement: $write);
        }
        throw new LogicException('A retained deletion has a read-only target condition.');
    }
}
