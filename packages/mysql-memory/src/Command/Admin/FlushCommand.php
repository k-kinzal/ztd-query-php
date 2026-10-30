<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Admin;

use MySqlMemory\Command\Access\AccountTables;
use MySqlMemory\Command\Access\Locks;
use MySqlMemory\Command\Command;
use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Server\Flush\Flush;
use SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushOption;
use SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushTables;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Executes FLUSH and FLUSH TABLES.
 *
 * Every form commits the open transaction. FLUSH TABLES closes cached handles while retaining
 * definitions and rows. FLUSH PRIVILEGES reads the grant tables under explicit locks. Options
 * execute once in server order, continuing log and status work after a privilege reload fails.
 * FLUSH BINARY LOGS and FLUSH LOGS close the active binary log file and open the next.
 * FLUSH RELAY LOGS FOR CHANNEL requires an existing channel. FLUSH TABLES ... WITH READ LOCK
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
    public function execute(\SqlSemantics\Statement\AnalyzedStatement $operation, Session $session, Context $context, Connection $connection): Reply
    {
        assert($operation instanceof MySqlStatement);
        $statement = $operation;
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
                if ($session->instance->dictionary->table($schema, $table->name->name->value, $session->temporaries) === null) {
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

        return new Completion(warnings: $session->diagnostics->stale ? 0 : $session->diagnostics->raised());
    }

    /**
     * Applies the option flags once in server order, preserving the first failure and later conditions.
     * The last RELAY LOGS item selects the channel, even when it omits FOR CHANNEL.
     * MySQL 5.6 clears only the requesting session; newer releases clear all active sessions.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/performance-schema-status-variable-tables.html.
     * https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_reload.cc.
     *
     * @throws SqlError When a privilege reload or relay flush fails
     */
    public function options(Flush $statement, Session $session): void
    {
        $items = [];
        foreach ($statement->items as $item) {
            $items[$item->option->value] = $item;
        }
        $failure = null;
        $following = [];
        foreach ([FlushOption::Privileges, FlushOption::BinaryLogs, FlushOption::RelayLogs, FlushOption::Status] as $option) {
            if (!isset($items[$option->value]) && !(isset($items[FlushOption::Logs->value]) && ($option === FlushOption::BinaryLogs || $option === FlushOption::RelayLogs))) {
                continue;
            }
            try {
                match ($option) {
                    FlushOption::Privileges => $this->privileges($session),
                    FlushOption::BinaryLogs => \MySqlMemory\Registry\BinaryLog::enabled($session) ? $session->instance->registry->binaryLog->rotate() : null,
                    FlushOption::RelayLogs => $this->relay($items[$option->value]->channel ?? null, $session),
                    FlushOption::Status => $this->status($session),
                };
            } catch (SqlError $error) {
                if ($failure === null) {
                    $failure = $error;
                } else {
                    $following[] = [$error->getCode(), $error->getMessage()];
                }
                array_push($following, ...$error->following);
            }
        }
        if ($failure !== null) {
            throw new SqlError($failure->error, $failure->getMessage(), $failure, $following);
        }
    }

    /**
     * Reloads the privilege caches after their required read locks have been checked.
     * Legacy releases load account and object grants separately; 5.6 also reads mysql.servers
     * in the caller's lock context. Each failed load contributes a condition, followed by
     * ER_UNKNOWN_ERROR when an account or object-grant load failed. Observed on MySQL 5.6–9.1.
     *
     * @throws SqlError When a required grant or server table is not locked
     */
    public function privileges(Session $session): void
    {
        $legacy = $session->settings()->legacy();
        $groups = $legacy ? [['user', 'db', 'proxies_priv'], ['tables_priv', 'columns_priv', 'procs_priv']] : [[...AccountTables::LEGACY, ...AccountTables::MODERN]];
        if ($session->settings()->release() === GrammarRelease::MySql5651) {
            $groups[] = ['servers'];
        }
        $errors = [];
        $failedGrants = false;
        foreach ($groups as $index => $tables) {
            try {
                if ($session->locks !== []) {
                    (new Locks())->required($session, array_map(static fn (string $table): QualifiedName => new QualifiedName(new Name($table), new Name('mysql')), $tables), false);
                }
                if ($index === 0) {
                    $session->instance->accounts->reload(!$legacy);
                }
            } catch (SqlError $error) {
                $errors[] = $error;
                $failedGrants = $failedGrants || $tables !== ['servers'];
            }
        }
        if ($errors !== []) {
            if ($failedGrants) {
                $errors[] = StatementError::UnknownError->error();
            }
            $first = array_shift($errors);
            throw new SqlError($first->error, $first->getMessage(), $first, array_map(static fn (SqlError $error): array => [$error->getCode(), $error->getMessage()], $errors));
        }
    }

    /**
     * Flushes the selected relay channel, or all channels when FOR CHANNEL is absent.
     *
     * @throws SqlError When the selected channel does not exist
     */
    public function relay(?Text $channel, Session $session): void
    {
        if ($channel === null) {
            return;
        }
        $name = (new \MySqlMemory\Command\Replication\ReplicaCommand())->channel($channel, $session->settings()->legacy(), $session->instance->registry);
        if ($session->settings()->legacy() && !$session->instance->registry->replicationAvailableAtStartup) {
            throw new SqlError(AdministrationError::ReplicaChannelMissing, "Slave channel '" . $name . "' does not exist.");
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
