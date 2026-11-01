<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Admin;

use MySqlMemory\Command\Command;
use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetPersist;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;

/**
 * Executes RESET PERSIST: removes persisted global variable settings.
 *
 * The saved settings are held by the instance. Resetting them does not change
 * the running global or session values. Naming a variable without a saved
 * setting is ER_VAR_DOES_NOT_EXIST, a warning under IF EXISTS.
 * The variable is named as the statement wrote it, after its component. The statement does not
 * commit the open transaction (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/reset-persist.html.
 *
 * @visibility MySqlMemory
 */
final class PersistCommand implements Command
{
    /**
     * Answers true.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Removes the persisted settings, refusing a variable that has none.
     */
    #[Override]
    public function execute(\SqlSemantics\Statement\AnalyzedStatement $operation, Session $session, Context $context, Connection $connection): Reply
    {
        assert($operation instanceof MySqlStatement);
        $statement = $operation;
        assert($statement instanceof ResetPersist);
        if ($statement->variable === null) {
            $session->variables->globals->persisted = [];
            return new Completion();
        }
        $name = ($statement->component === null ? '' : $statement->component->value . '.') . $statement->variable->value;
        $key = ($statement->component === null ? '' : $statement->component->value . '.') . strtolower($statement->variable->value);
        if (array_key_exists($key, $session->variables->globals->persisted)) {
            unset($session->variables->globals->persisted[$key]);

            return new Completion();
        }
        if (!$statement->ifExists) {
            throw AdministrationError::VariableNotPersisted->error($name);
        }
        $session->diagnostics->warning(AdministrationError::VariableNotPersisted, AdministrationError::VariableNotPersisted->message($name));

        return new Completion(0, 0, $session->diagnostics->raised());
    }
}
