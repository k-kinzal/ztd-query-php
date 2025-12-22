<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Mysqli;

use mysqli;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Connection\StatementInterface;

/**
 * mysqli adapter implementing ConnectionInterface for ZTD layer.
 *
 * This class wraps a mysqli instance and provides the minimal interface
 * required by the ZTD session for executing queries.
 */
final class MysqliConnection implements ConnectionInterface
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * {@inheritDoc}
     *
     * @throws DatabaseException On database error.
     */
    public function query(string $sql): StatementInterface|false
    {
        $result = $this->mysqli->query($sql);

        if ($result === false) {
            if ($this->mysqli->errno !== 0) {
                throw new DatabaseException(
                    $this->mysqli->error,
                    $this->mysqli->errno,
                    $this->mysqli->errno
                );
            }
            return false;
        }

        if ($result === true) {
            return new MysqliResultStatement(null, (int) $this->mysqli->affected_rows);
        }

        return new MysqliResultStatement($result, (int) $this->mysqli->affected_rows);
    }

}
