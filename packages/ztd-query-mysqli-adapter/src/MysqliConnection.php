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

    /**
     * What the connection says after a statement has run.
     */
    private ConnectionState $state;

    /**
     * Binds the instance to what it will work from.
     *
     * @param mysqli $mysqli Connection the statements run on
     * @param ConnectionProperties|null $properties What the connection answers about itself, or null to read it off the connection
     */
    public function __construct(mysqli $mysqli, ?ConnectionProperties $properties = null)
    {
        $this->mysqli = $mysqli;
        $this->state = new ConnectionState($properties ?? new MysqliProperties($mysqli));
    }

    /**
     * {@inheritDoc}
     *
     * @param string $sql Statement as it was written
     *
     * @return StatementInterface|false What the statement answered, or false where it did not run and the driver said nothing
     *
     * @throws DatabaseException On database error.
     */
    public function query(string $sql): StatementInterface|false
    {
        $result = $this->mysqli->query($sql);

        if ($result === false) {
            $errorNumber = $this->state->errorNumber();
            if ($errorNumber !== 0) {
                throw new DatabaseException(
                    $this->state->errorMessage(),
                    $errorNumber,
                    $errorNumber
                );
            }

            return false;
        }

        $affectedRows = $this->state->affectedRows();
        if ($result === true) {
            return new MysqliResultStatement(null, $affectedRows);
        }

        return new MysqliResultStatement($result, $affectedRows);
    }

}
