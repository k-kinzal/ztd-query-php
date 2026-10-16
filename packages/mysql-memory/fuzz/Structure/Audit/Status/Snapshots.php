<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit\Status;

use Fuzz\Structure\Execution;
use Fuzz\Target\Status\Snapshot;
use mysqli;
use mysqli_result;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowStatus;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;

/**
 * Captures the same two status requests through the native and direct interfaces.
 * The direct interface has no transport bytes. Native bytes come from mysqlnd,
 * independently of the server's own byte counters.
 */
final class Snapshots
{
    /**
     * Samples native global and session state without extra diagnostic requests.
     */
    public function native(mysqli $connection): ?Snapshot
    {
        $instant = microtime(true);
        $first = mysqli_get_client_stats()['bytes_received'] ?? null;
        $global = $this->read($connection, 'SHOW GLOBAL STATUS');
        $second = mysqli_get_client_stats()['bytes_received'] ?? null;
        $session = $this->read($connection, 'SHOW SESSION STATUS');
        $last = mysqli_get_client_stats()['bytes_received'] ?? null;
        if ($global === null || $session === null || !is_string($first) || !is_string($second) || !is_string($last) || !ctype_digit($first) || !ctype_digit($second) || !ctype_digit($last)) {
            return null;
        }

        return new Snapshot($global, $session, bcsub($last, $first), bcsub($last, $second), $instant);
    }

    /**
     * Samples directly constructed requests; no SQL is submitted to memory.
     */
    public function memory(Session $session): ?Snapshot
    {
        $instant = microtime(true);
        $execution = new Execution();
        $global = $execution->runIn($session, new ShowStatus(VariableScope::Global));
        $local = $execution->runIn($session, new ShowStatus(VariableScope::Session));
        $globalValues = $global instanceof ResultSet ? Snapshot::index($global->rows) : null;
        $sessionValues = $local instanceof ResultSet ? Snapshot::index($local->rows) : null;

        return $globalValues === null || $sessionValues === null ? null : new Snapshot($globalValues, $sessionValues, '0', '0', $instant);
    }

    /**
     * @return array<string, string>|null A unique variable-name map
     */
    public function read(mysqli $connection, string $sql): ?array
    {
        $result = $connection->query($sql);
        if (!$result instanceof mysqli_result) {
            return null;
        }
        $rows = $result->fetch_all(MYSQLI_NUM);
        $result->free();

        return Snapshot::index($rows);
    }
}
