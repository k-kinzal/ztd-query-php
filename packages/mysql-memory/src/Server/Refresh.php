<?php

declare(strict_types=1);

namespace MySqlMemory\Server;

use MySqlMemory\Command\Admin\FlushCommand;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Protocol\PayloadReader;
use MySqlMemory\Session\State\StatementCounters;

/**
 * Executes the legacy COM_REFRESH bit mask without generating extra SQL requests.
 *
 * Refresh commits the active transaction, counts one Com_flush and records its deprecation
 * in SHOW WARNINGS and the OK packet. The mysqlnd refresh API retains its earlier warning count.
 * Existing diagnostic conditions remain and any refresh condition is appended to them.
 * MySQL 8.3 removed this packet;
 * newer C clients implement mysql_refresh with SQL instead, while mysqlnd still sends it.
 * Source: https://dev.mysql.com/doc/c-api/8.0/en/mysql-refresh.html.
 * https://dev.mysql.com/doc/relnotes/mysql/8.4/en/news-8-4-0.html.
 *
 * @visibility MySqlMemory
 */
final class Refresh
{
    /**
     * @param Client $client The authenticated connection
     */
    public function __construct(public readonly Client $client)
    {
    }

    /**
     * Applies the mask and sends its completion or recorded error.
     */
    public function answer(PayloadReader $reader): bool
    {
        $session = $this->client->session();
        $session->activity->begin();
        StatementCounters::received($session);
        $session->diagnostics->previous = [$session->diagnostics->raised(), $session->diagnostics->errors()];
        $session->diagnostics->limit = $session->variables->count('max_error_count', 1024);
        $session->diagnostics->stale = false;
        try {
            if (version_compare($session->instance->version, '8.3', '>=')) {
                throw StatementError::UnknownCommand->error();
            }
            StatementCounters::command($session, 'Com_flush');
            if (version_compare($session->instance->version, '5.7', '>=')) {
                $session->diagnostics->warning(StatementError::DeprecatedSyntax, StatementError::DeprecatedSyntax->message('COM_REFRESH', 'FLUSH statement'));
            }
            $this->apply($reader->integer(1));
        } catch (SqlError $error) {
            $session->variables->rowCount = -1;
            $session->diagnostics->error($error->getCode(), $error->getMessage());

            return $this->client->send($this->client->messages->error($error->getCode(), $error->sqlState(), $error->getMessage()));
        }
        $session->variables->rowCount = 0;

        return $this->client->send($this->client->messages->ok(0, 0, $this->client->status(), $session->diagnostics->count()));
    }

    /**
     * Applies table, log, status and replication flags to the corresponding in-memory state.
     * Grant data is already current; host and thread caches have no retained emulated entries.
     */
    public function apply(int $flags): void
    {
        $session = $this->client->session();
        $registry = $session->instance->registry;
        $session->transaction->commit();
        if (($flags & 2) !== 0) {
            $registry->binaryLog->rotate();
        }
        if (($flags & 4) !== 0) {
            $session->instance->dictionary->cache->close();
            $registry->status->add('Flush_commands');
        }
        if (($flags & 16) !== 0) {
            (new FlushCommand())->status($session);
        }
        if (($flags & 128) !== 0) {
            $registry->binaryLog->reset();
        }
        if (($flags & 64) !== 0) {
            $registry->replication->reset();
        }
    }
}
