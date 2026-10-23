<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Definition;

use LogicException;
use MySqlMemory\Command\Account\Names;
use MySqlMemory\Command\Program\TriggerCommand;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use MySqlMemory\Session\State\StatementCounters;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\TriggerSchemaMismatch;

/**
 * Resolves a contradictory trigger header after completed input conditions.
 *
 * Only this input boundary reads SQL for grammar notices. Catalog lookup uses
 * the retained semantic identities; no invalid CreateTrigger is constructed.
 *
 * @visibility MySqlMemory
 */
final class TriggerInput
{
    /**
     * Preserves command counts, preparation and the implicit commit before target lookup.
     *
     * @throws LogicException When contradictory explicit databases unexpectedly bind
     */
    public function refused(RejectedInput $failure, string $statement, Session $session): SqlError
    {
        $problem = $failure->problem;
        Check::invariant($problem instanceof TriggerSchemaMismatch, 'The refusal retains contradictory trigger qualifiers.');
        $syntax = (new TableInput())->warnings($problem->message(), $statement, $session);
        if ($syntax !== null) {
            return $syntax;
        }
        try {
            (new Names($session->settings()->release()))->check([$problem->definer]);
            if ($session->preparation->inputPrepared) {
                throw StatementError::UnsupportedPreparedStatement->error();
            }
            StatementCounters::command($session, 'Com_create_trigger');
            $session->transaction->commit();
            (new TriggerCommand())->relation($problem->name, $problem->table, $session);
            throw new LogicException('Distinct explicit trigger databases cannot bind to one target.');
        } catch (SqlError $error) {
            $session->diagnostics->error($error->getCode(), $error->getMessage());
            return new SqlError($error->error, $error->getMessage(), $failure, recorded: true);
        }
    }
}
