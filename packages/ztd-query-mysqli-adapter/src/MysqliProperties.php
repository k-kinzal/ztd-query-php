<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Mysqli;

use mysqli;

/**
 * Reads a mysqli connection's properties off the connection itself.
 *
 * mysqli answers its properties from the extension rather than declaring them,
 * so there is no reaching one by a name held in a variable: every name it has
 * is written out here.
 */
final class MysqliProperties implements ConnectionProperties
{
    /**
     * Binds the reader to the connection it reads from.
     *
     * @param mysqli $connection Connection whose properties are answered
     */
    public function __construct(private readonly mysqli $connection)
    {
    }

    /**
     * {@inheritDoc}
     *
     * @param string $name Property as it was written
     *
     * @return mixed What the connection has under that name, or null where mysqli has no such property
     */
    public function named(string $name): mixed
    {
        return match ($name) {
            'affected_rows' => $this->connection->affected_rows,
            'client_info' => $this->connection->client_info,
            'client_version' => $this->connection->client_version,
            'connect_errno' => $this->connection->connect_errno,
            'connect_error' => $this->connection->connect_error,
            'errno' => $this->connection->errno,
            'error' => $this->connection->error,
            'error_list' => $this->connection->error_list,
            'field_count' => $this->connection->field_count,
            'host_info' => $this->connection->host_info,
            'info' => $this->connection->info,
            'insert_id' => $this->connection->insert_id,
            'server_info' => $this->connection->server_info,
            'server_version' => $this->connection->server_version,
            'sqlstate' => $this->connection->sqlstate,
            'protocol_version' => $this->connection->protocol_version,
            'thread_id' => $this->connection->thread_id,
            'warning_count' => $this->connection->warning_count,
            default => null,
        };
    }
}
