<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Admin;

use MySqlMemory\Command\Command;
use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Server\Flush\Flush;
use SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushOption;
use SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushTables;
use SqlSemantics\Statement\Operation;

/**
 * Executes FLUSH and FLUSH TABLES.
 *
 * Every form commits the open transaction. FLUSH TABLES closes cached handles while retaining
 * definitions and rows. Most other forms only succeed. FLUSH BINARY LOGS and FLUSH LOGS close the
 * active binary log file and open the next. FLUSH RELAY LOGS FOR CHANNEL fails for a channel other
 * than the default one, which is the only channel of the server. FLUSH TABLES ... WITH READ LOCK
 * and FLUSH TABLES ... FOR EXPORT need each table they name to exist, and lock the tables for
 * reading as LOCK TABLES ... READ does, until UNLOCK TABLES; the global read lock of FLUSH TABLES
 * WITH READ LOCK is not modelled, the session being alone on the server (verified on a live 8.4
 * server). FLUSH HOSTS, deprecated in MySQL 8.0.23 and removed in 8.3, warns once however often
 * the statement names it (verified on live 5.7 and 8.0 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/flush.html,
 * https://dev.mysql.com/doc/relnotes/mysql/8.3/en/news-8-3-0.html.
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
        if ($statement instanceof FlushTables) {
            $this->tables($statement, $session);
        }
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
                (new \MySqlMemory\Session\Access\OpenedTables())->open($schema, $table->name->name->value, $session);
            }
            if ($locks !== []) {
                $session->locks = $locks;
            }
        }
        if ($statement instanceof Flush) {
            $hosts = array_filter($statement->items, static fn ($item): bool => $item->option === FlushOption::Hosts);
            $release = $session->settings()->release();
            if ($hosts !== [] && $release !== GrammarRelease::MySql5651 && $release !== GrammarRelease::MySql5744) {
                $context->warning(StatementError::DeprecatedSyntax, 'FLUSH HOSTS', 'TRUNCATE TABLE performance_schema.host_cache');
            }
            $this->options($statement, $session);
        }

        return new Completion();
    }

    /**
     * Applies status resets and log flushes in written order.
     * MySQL 5.6 clears only the requesting session; newer releases clear all active sessions.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/performance-schema-status-variable-tables.html.
     *
     * @throws \MySqlMemory\Error\SqlError When the requested relay channel does not exist
     */
    public function options(Flush $statement, Session $session): void
    {
        foreach ($statement->items as $item) {
            if ($item->option === FlushOption::Privileges) {
                $session->instance->accounts->reload(!$session->settings()->legacy());
            }
            if ($item->option === FlushOption::Status) {
                $this->status($session);
            }
            $channel = $item->channel === null ? '' : (new \SqlSemantics\Platform\MySql\Rules\Replication\ChannelName())->value($item->channel);
            if ($item->option === FlushOption::RelayLogs && $item->channel !== null) {
                (new \MySqlMemory\Command\Replication\ReplicaCommand())->channel($item->channel, $session->settings()->legacy());
                if ($session->settings()->legacy() && (int) $session->variables->read('server_id') === 0) {
                    throw new \MySqlMemory\Error\SqlError(AdministrationError::ReplicaChannelMissing, "Slave channel '" . $channel . "' does not exist.");
                }
            }
            if ($item->option === FlushOption::BinaryLogs || $item->option === FlushOption::Logs) {
                $session->instance->registry->binaryLog->rotate();
            }
        }
    }

    /**
     * Resets the scoped counters and connection peak for SQL FLUSH STATUS and COM_REFRESH.
     */
    public function status(Session $session): void
    {
        $session->instance->registry->status->clear($session->settings()->release() === GrammarRelease::MySql5651 ? $session->id : null);
        $session->instance->registry->status->clearGlobal('Aborted_clients');
        $session->instance->registry->status->flushedAt = $session->instance->registry->threads->now();
        $session->instance->registry->threads->resetMaximum();
    }

    /**
     * Closes cached handles while retaining definitions, rows and HANDLER cursor names.
     * Flush_commands counts a flush of all tables, including WITH READ LOCK, but not named tables.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/server-status-variables.html#statvar_Flush_commands.
     */
    public function tables(FlushTables $statement, Session $session): void
    {
        $cache = $session->instance->dictionary->cache;
        if ($statement->tables === []) {
            $session->instance->registry->status->add('Flush_commands');
            $cache->close();
            foreach ($session->instance->sessions as $connection) {
                $connection->get()?->databaseGrants->clear();
            }

            return;
        }
        foreach ($statement->tables as $table) {
            $schema = $table->name->schema->value ?? $session->variables->database;
            $cache->close($schema, $table->name->name->value);
            if ($schema === 'mysql' && $table->name->name->value === 'db') {
                foreach ($session->instance->sessions as $connection) {
                    $connection->get()?->databaseGrants->clear();
                }
            }
        }
    }
}
