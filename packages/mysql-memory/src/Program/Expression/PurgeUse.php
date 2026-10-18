<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Expression;

use MySqlMemory\Command\Replication\PurgeErrors;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Problem\Stages;
use MySqlMemory\Session\Session;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;

/**
 * Prepares a retained log cutoff in its PURGE role without reconstructing SQL.
 *
 * Input conditions precede preparation. A reached operand's preparation failure
 * retains the following PURGE argument condition, including stored instructions.
 *
 * @visibility MySqlMemory
 */
final class PurgeUse
{
    /**
     * Answers the first cutoff condition and the command's following diagnostic.
     */
    public function error(RowOperand|DeferredOperand $moment, Session $session): SqlError
    {
        $database = $session->variables->database;
        $context = $session->semantics()->context($session->instance->dictionary->declarations(), true, $database === '' ? null : new SearchPath($database), $session->resolution());
        $derivation = new Derivation($context);
        $derivation->scalar($moment, $derivation->environment());
        foreach ($derivation->facts()->diagnostics as $problem) {
            if (Stages::parsed($problem)) {
                return (new Errors())->error($problem, $session);
            }
        }
        $unbound = $this->unbound($moment, $session);
        if ($unbound !== null) {
            return $unbound;
        }
        try {
            (new RowUse())->raise($moment, $session, false);
        } catch (SqlError $error) {
            return (new PurgeErrors())->moment($error, true);
        }
    }

    /**
     * Starts a reached stored instruction and raises its cutoff condition.
     *
     * @throws SqlError Always, after the retained operand has been prepared
     */
    public function raise(RowOperand|DeferredOperand $moment, Session $session): never
    {
        \MySqlMemory\Session\State\StatementCounters::query($session);
        \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_purge_before_date');
        $session->diagnostics->clear();
        throw $this->error($moment, $session);
    }

    /**
     * Finds an unqualified stored call whose database must bind before preparation.
     */
    public function unbound(\SqlSemantics\Statement\Node $moment, Session $session): ?SqlError
    {
        if ($session->variables->database !== '') {
            return null;
        }
        $walker = new \MySqlMemory\Evaluation\Compile\Walker();
        $calls = [...$walker->find($moment, \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall::class), ...$walker->find($moment, \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredCall::class)];
        foreach ($calls as $call) {
            if ($call->schema === null && !$call->native($session->settings()->release())) {
                return (new Errors())->routine($call, $session);
            }
        }
        return null;
    }
}
