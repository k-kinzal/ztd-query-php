<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Mysqli;

use mysqli;
use mysqli_result;
use mysqli_stmt;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Connection\StatementInterface;

/**
 * mysqli prepared statement adapter implementing StatementInterface for ZTD layer.
 *
 * This class wraps a mysqli_stmt and provides the minimal interface
 * required by the ZTD session for executing statements and fetching results.
 */
final class MysqliStatement implements StatementInterface
{
    private mysqli_stmt $statement;

    private mysqli $mysqli;

    /**
     * @var mysqli_result|false|null
     */
    private mysqli_result|false|null $result = null;

    public function __construct(mysqli_stmt $statement, mysqli $mysqli)
    {
        $this->statement = $statement;
        $this->mysqli = $mysqli;
    }

    /**
     * {@inheritDoc}
     *
     * @throws DatabaseException On database error.
     */
    public function execute(?array $params = null): bool
    {
        if ($params !== null && $params !== []) {
            if (!$this->statement->execute($params)) {
                if ($this->mysqli->errno !== 0) {
                    throw new DatabaseException(
                        $this->mysqli->error,
                        $this->mysqli->errno,
                        $this->mysqli->errno
                    );
                }
                return false;
            }
        } else {
            if (!$this->statement->execute()) {
                if ($this->mysqli->errno !== 0) {
                    throw new DatabaseException(
                        $this->mysqli->error,
                        $this->mysqli->errno,
                        $this->mysqli->errno
                    );
                }
                return false;
            }
        }

        // get_result() is deferred so ZtdMysqliStatement::get_result() can call it on the underlying stmt

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAll(): array
    {
        if ($this->result === null) {
            $this->result = $this->statement->get_result();
        }

        if ($this->result === false) {
            $this->statement->close();
            return [];
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $this->result->fetch_all(MYSQLI_ASSOC);

        // Free the result to avoid "Commands out of sync" errors
        $this->result->free();
        $this->result = null;

        $this->statement->close();

        return $rows;
    }

    /**
     * {@inheritDoc}
     */
    public function rowCount(): int
    {
        return (int) $this->statement->affected_rows;
    }
}
