<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Admin;

use MySqlMemory\Command\Replication\LegacyReplication;
use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Registry\BinaryLog;
use MySqlMemory\Session\Session;

/**
 * Applies the resource operations of a legacy COM_REFRESH request.
 *
 * @visibility MySqlMemory
 */
final class RefreshFlags
{
    /**
     * @param Session $session The session requesting the refresh
     */
    public function __construct(public readonly Session $session)
    {
    }

    /**
     * Applies table, log, status and replication flags to the corresponding in-memory state.
     * Grant data is already current; host and thread caches have no retained emulated entries.
     */
    public function apply(int $flags): void
    {
        $session = $this->session;
        $registry = $session->instance->registry;
        $session->transaction->commit();
        if (($flags & 2) !== 0 && BinaryLog::enabled($session)) {
            $registry->binaryLog->rotate();
        }
        if (($flags & 4) !== 0) {
            $session->instance->dictionary->cache->close();
            $registry->status->add('Flush_commands');
        }
        if (($flags & 16) !== 0) {
            (new FlushCommand())->status($session);
        }
        $this->reset($flags);
    }

    /**
     * Resets the binary log and replica state, retaining both legacy errors when both fail.
     * MySQL 5.6 requires an open binary log; 5.6 and 5.7 require a server ID for a replica.
     *
     * @throws SqlError When a requested reset has no configured resource
     */
    public function reset(int $flags): void
    {
        $session = $this->session;
        $errors = [];
        if (($flags & 128) !== 0) {
            if (str_starts_with($session->instance->version, '5.6.') && !BinaryLog::enabled($session)) {
                $errors[] = AdministrationError::BinlogClosed->error('RESET MASTER');
            } else {
                $session->instance->registry->binaryLog->reset();
            }
        }
        if (($flags & 64) !== 0) {
            if ($session->settings()->legacy() && (int) $session->variables->read('server_id') === 0) {
                $errors[] = new SqlError(AdministrationError::ReplicaNotInitialized, LegacyReplication::UNCONFIGURED);
            } else {
                $session->instance->registry->replication->reset();
            }
        }
        if ($errors !== []) {
            $first = array_shift($errors);
            throw new SqlError($first->error, $first->getMessage(), null, array_map(static fn (SqlError $error): array => [$error->getCode(), $error->getMessage()], $errors));
        }
    }
}
