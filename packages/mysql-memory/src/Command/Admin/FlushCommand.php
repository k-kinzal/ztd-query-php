<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Admin;

use MySqlMemory\Command\Command;
use MySqlMemory\Error\AdministrationError;
use MySqlMemory\Error\QueryError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Server\Flush\Flush;
use SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushOption;
use SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushTables;
use SqlSemantics\Statement\Operation;

/**
 * Executes FLUSH and FLUSH TABLES.
 *
 * Every form commits the open transaction. The emulated server keeps no caches, logs to no file
 * and holds no table open, so most forms only succeed. FLUSH BINARY LOGS and FLUSH LOGS close the
 * active binary log file and open the next. FLUSH RELAY LOGS FOR CHANNEL fails for a channel other
 * than the default one, which is the only channel of the server. FLUSH TABLES ... WITH READ LOCK
 * and FLUSH TABLES ... FOR EXPORT need each table they name to exist, and lock the tables for
 * reading as LOCK TABLES ... READ does, until UNLOCK TABLES; the global read lock of FLUSH TABLES
 * WITH READ LOCK is not modelled, the session being alone on the server (verified on a live 8.4
 * server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/flush.html.
 *
 * @visibility MySqlMemory
 */
final class FlushCommand implements Command
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
     * Flushes what the statement names.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        $session->transaction->commit();
        if ($statement instanceof FlushTables && $statement->lock !== null) {
            $locks = [];
            foreach ($statement->tables as $table) {
                $schema = $table->name->schema->value ?? $session->variables->database;
                if ($schema === '') {
                    throw QueryError::NoDatabase->error();
                }
                if ($session->instance->dictionary->schema($schema) === null) {
                    throw QueryError::BadDatabase->error($schema);
                }
                if ($session->instance->dictionary->table($schema, $table->name->name->value) === null) {
                    throw QueryError::NoSuchTable->error($schema, $table->name->name->value);
                }
                $locks[] = [$schema, $table->name->name->value, $table->name->name->value, false];
            }
            if ($locks !== []) {
                $session->locks = $locks;
            }
        }
        if ($statement instanceof Flush) {
            foreach ($statement->items as $item) {
                if ($item->option === FlushOption::RelayLogs && $item->channel !== null && $item->channel->value !== '') {
                    throw AdministrationError::ReplicaChannelMissing->error($item->channel->value);
                }
                if ($item->option === FlushOption::BinaryLogs || $item->option === FlushOption::Logs) {
                    $session->instance->registry->binaryLog->rotate();
                }
            }
        }

        return new Completion();
    }
}
