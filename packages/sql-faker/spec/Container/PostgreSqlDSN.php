<?php

declare(strict_types=1);

namespace Spec\Container;

use Testcontainers\Containers\WaitStrategy\PDO\DSN;
use Testcontainers\Utility\Stringable;

/**
 * Mutable DSN builder used by the PostgreSQL spec container wait strategy.
 */
final class PostgreSqlDSN implements DSN, Stringable
{
    private ?string $host = null;
    private ?int $port = null;
    private ?string $dbname = null;

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * @param string $host
     * @return $this
     */
    public function withHost($host): self
    {
        $this->host = $host;
        return $this;
    }

    /**
     * Returns the configured host component.
     */
    public function getHost(): ?string
    {
        return $this->host;
    }

    /**
     * @param int $port
     * @return $this
     */
    public function withPort($port): self
    {
        $this->port = $port;
        return $this;
    }

    /**
     * Returns the configured port component.
     */
    public function getPort(): ?int
    {
        return $this->port;
    }

    /**
     * @return $this
     */
    public function withDbname(string $dbname): self
    {
        $this->dbname = $dbname;
        return $this;
    }

    /**
     * Formats the accumulated connection settings as a PDO PostgreSQL DSN.
     */
    public function toString(): string
    {
        if ($this->host === null) {
            throw new \LogicException('Host is required');
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
     * Indicates that the wait strategy must inject both host and port before use.
     */
    public function requiresHostPort(): bool
    {
        return true;
    }
}
