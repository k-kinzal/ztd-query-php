<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Access;

use MySqlMemory\Command\Command;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\LockMode;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\LockTables;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\UnlockTables;
use SqlSemantics\Statement\Operation;

/**
 * Executes LOCK TABLES and UNLOCK TABLES: the tables a session may use until it unlocks them.
 *
 * LOCK TABLES checks each table in written order, its database first, then commits the open
 * transaction and replaces the locks the session held. UNLOCK TABLES commits the open
 * transaction when the session held locks, and releases them. The session is alone on the
 * emulator, so a lock never waits. Every rule was verified on a live 8.4 server.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/lock-tables.html,
 * https://dev.mysql.com/doc/refman/8.4/en/implicit-commit.html.
 *
 * @visibility MySqlMemory
 */
final class LockTablesCommand implements Command
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
     * Locks or unlocks the tables.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        if ($statement instanceof UnlockTables) {
            if ($session->locks !== []) {
                $session->transaction->commit();
            }
            $session->locks = [];

            return new Completion();
        }
        assert($statement instanceof LockTables);
        $database = $session->variables->database;
        $locks = [];
        foreach ($statement->locks as $lock) {
            $schema = $lock->table->schema->value ?? $database;
            if ($schema === '') {
                throw QueryError::NoDatabase->error();
            }
            if ($session->instance->dictionary->schema($schema) === null) {
                throw \MySqlMemory\Session\Problem\Errors::unknown($schema, $lock->table->name->value, $session->settings()->release());
            }
            if ($session->instance->dictionary->table($schema, $lock->table->name->value) === null && !isset($session->instance->dictionary->schema($schema)?->views[$lock->table->name->value])) {
                throw QueryError::NoSuchTable->error($schema, $lock->table->name->value);
            }
            $locks[] = [$schema, $lock->table->name->value, $lock->alias->value ?? $lock->table->name->value, $lock->mode === LockMode::Write || $lock->mode === LockMode::LowPriorityWrite];
        }
        $session->transaction->commit();
        $session->locks = $locks;

        return new Completion();
    }
}
