<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Definition;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Program\Definition\IndexUse;
use MySqlMemory\Session\Session;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedIndexTemplate;

/**
 * Preserves completed input notices before binding a retained index instruction.
 *
 * @visibility MySqlMemory
 */
final class IndexInput
{
    /**
     * Reports the reached condition or retains the complete prepared instruction.
     *
     * @throws DeferredPreparation When preparation preserves the index for execution
     */
    public function refused(RejectedInput $failure, string $statement, Session $session): SqlError
    {
        $problem = $failure->problem;
        \SqlSemantics\Diagnostic\Check::invariant($problem instanceof RefusedIndexTemplate, 'An index refusal retains its complete instruction.');
        $syntax = (new TableInput())->warnings($problem->message(), $statement, $session);
        if ($syntax !== null) {
            return $syntax;
        }
        try {
            $use = new IndexUse();
            if ($session->preparation->inputPrepared) {
                $use->header($problem->definition, $session);
                throw new DeferredPreparation(new \MySqlMemory\Command\Prepared\PendingIndex($problem->definition, $session->variables->database));
            }
            \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_create_index');
            $use->raise($problem->definition, $session, false);
        } catch (SqlError $error) {
            if (!$error->recorded) {
                $session->diagnostics->error($error->getCode(), $error->getMessage(), $error->signalled);
            }
            foreach ($error->following as [$code, $message]) {
                $session->diagnostics->error($code, $message);
            }
            return new SqlError($error->error, $error->getMessage(), $failure, [], $error->signalled, $error->getCode(), true);
        }
    }
}
