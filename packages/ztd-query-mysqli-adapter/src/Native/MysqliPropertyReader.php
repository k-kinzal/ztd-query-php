<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Mysqli\Native;

use mysqli;

/**
 * Reads the native connection properties exposed by the proxy.
 */
final class MysqliPropertyReader
{
    /**
     * Read a known native property without invoking a proxy property handler.
     *
     */
    public function read(mysqli $connection, string $name): mixed
    {
        return match ($name) {
            'affected_rows' => $connection->affected_rows,
            'client_info' => $connection->client_info,
            'client_version' => $connection->client_version,
            'connect_errno' => $connection->connect_errno,
            'connect_error' => $connection->connect_error,
            'errno' => $connection->errno,
            'error' => $connection->error,
            'error_list' => $connection->error_list,
            'field_count' => $connection->field_count,
            'host_info' => $connection->host_info,
            'info' => $connection->info,
            'insert_id' => $connection->insert_id,
            'server_info' => $connection->server_info,
            'server_version' => $connection->server_version,
            'sqlstate' => $connection->sqlstate,
            'protocol_version' => $connection->protocol_version,
            'thread_id' => $connection->thread_id,
            'warning_count' => $connection->warning_count,
            default => null,
        };
    }

}
