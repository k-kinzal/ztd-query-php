<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use LogicException;
use Testcontainers\Containers\WaitStrategy\PDO\DSN;
use Testcontainers\Utility\Stringable;

/**
 * PostgreSQL DSN for Testcontainers PDO wait strategy.
 */
final class PostgreSqlDSN implements DSN, Stringable
{
    private ?string $host = null;
    private ?int $port = null;
    private ?string $dbname = null;

    /**
     * __to string.
     *
     * @return string
     */
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
     * Answers host.
     *
     * @return ?string
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
     * Answers port.
     *
     * @return ?int
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
     * @throws LogicException
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
     * Requires host port.
     *
     * @return bool
     */
    public function requiresHostPort(): bool
    {
        return true;
    }
}
