<?php

declare(strict_types=1);

namespace Fuzz\Container;

use LogicException;
use Testcontainers\Containers\WaitStrategy\PDO\DSN;
use Testcontainers\Utility\Stringable;

/**
 * Builds the PDO connection string the wait strategy uses to reach PostgreSQL.
 *
 * Testcontainers only learns the mapped host and port once the container is
 * running, so the DSN is assembled incrementally through the `with*()` methods
 * and rendered when the wait strategy is ready to connect.
 */
final class PostgreSqlDSN implements DSN, Stringable
{
    private ?string $host = null;
    private ?int $port = null;
    private ?string $dbname = null;

    /**
     * Renders the connection string.
     *
     * @return string PDO connection string for the configured endpoint
     *
     * @throws LogicException When no host has been configured yet
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Sets the host the connection should target.
     *
     * @param string $host Host PostgreSQL is reachable on
     *
     * @return $this
     */
    public function withHost($host): self
    {
        $this->host = $host;
        return $this;
    }

    /**
     * Reports the configured host.
     *
     * @return string|null Configured host, or null while none has been set
     */
    public function getHost(): ?string
    {
        return $this->host;
    }

    /**
     * Sets the port the connection should target.
     *
     * @param int $port Port PostgreSQL is reachable on
     *
     * @return $this
     */
    public function withPort($port): self
    {
        $this->port = $port;
        return $this;
    }

    /**
     * Reports the configured port.
     *
     * @return int|null Configured port, or null while none has been set
     */
    public function getPort(): ?int
    {
        return $this->port;
    }

    /**
     * Sets the database the connection should open.
     *
     * @param string $dbname Database name to connect to
     *
     * @return $this
     */
    public function withDbname(string $dbname): self
    {
        $this->dbname = $dbname;
        return $this;
    }

    /**
     * Renders the connection string.
     *
     * Port and database are optional because PostgreSQL falls back to its
     * defaults for both, but a host is not: without one there is nothing to
     * connect to, and the wait strategy would otherwise retry against an
     * address it never received.
     *
     * @return string PDO connection string for the configured endpoint
     *
     * @throws LogicException When no host has been configured yet
     */
    public function toString(): string
    {
        if ($this->host === null) {
            throw new LogicException('Host is required');
        }
        $dsn = sprintf('pgsql:host=%s;', $this->host);
        if ($this->port !== null) {
            $dsn .= 'port=' . $this->port . ';';
        }
        if ($this->dbname !== null) {
            $dsn .= 'dbname=' . $this->dbname . ';';
        }
        return $dsn;
    }

    /**
     * Tells the wait strategy that this DSN needs a mapped host and port.
     *
     * @return bool Always true
     */
    public function requiresHostPort(): bool
    {
        return true;
    }
}
