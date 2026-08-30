<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Mysqli\Driver;

use mysqli;
use mysqli_result;
use mysqli_stmt;
use ZtdQuery\Adapter\Mysqli\ZtdMysqliStatement;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Platform\ResultColumnTypeResolver;

/**
 * mysqli prepared statement adapter implementing StatementInterface for ZTD layer.
 *
 * This class wraps a mysqli_stmt and provides the minimal interface
 * required by the ZTD session for executing statements and fetching results.
 *
 * @phpstan-import-type Row from StatementInterface
 */
final class MysqliStatement implements StatementInterface
{
    private mysqli_stmt $statement;

    /**
     * What the connection says after a statement has run.
     */
    private ConnectionState $state;

    /**
     * @var mysqli_result|false|null
     */
    private mysqli_result|false|null $result = null;

    /**
     * Binds the instance to what it will work from.
     *
     * @param mysqli_stmt $statement Statement the driver prepared
     * @param mysqli $mysqli Connection it was prepared on
     * @param ConnectionProperties|null $properties What the connection answers about itself, or null to read it off the connection
     */
    public function __construct(mysqli_stmt $statement, mysqli $mysqli, ?ConnectionProperties $properties = null)
    {
        $this->statement = $statement;
        $this->state = new ConnectionState($properties ?? new MysqliProperties($mysqli));
    }

    /**
     * {@inheritDoc}
     *
     * The result is not read here. ZtdMysqliStatement::get_result() reads it off
     * the statement the driver prepared, and a result read twice is no result at
     * all, so this leaves it where that can still find it.
     *
     * @throws DatabaseException On database error.
     */
    public function execute(?array $params = null): bool
    {
        if ($params !== null && $params !== []) {
            if (!$this->statement->execute($params)) {
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
        } else {
            if (!$this->statement->execute()) {
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
        }

        return true;
    }

    /**
     * {@inheritDoc}
     *
     * The result is freed as soon as it is read, because a statement left
     * holding one puts the connection out of step with the server.
     */
    public function fetchAll(): array
    {
        $result = $this->loadResult();

        if ($result === false) {
            $this->statement->close();
            return [];
        }

        /** @var list<Row> $rows */
        $rows = $result->fetch_all(MYSQLI_ASSOC);

        $result->free();
        $this->result = null;

        $this->statement->close();

        return $rows;
    }

    /**
     * {@inheritDoc}
     */
    public function resultColumns(ResultColumnTypeResolver $typeResolver): array
    {
        $result = $this->loadResult();
        if ($result === false) {
            return [];
        }

        return MysqliResultColumnExtractor::extract($result, $typeResolver);
    }

    /**
     * {@inheritDoc}
     *
     * The count is read off the connection rather than off the statement, for
     * the same reason the error is: a statement built without a live connection
     * refuses to answer its own properties, and the connection's count is the
     * one the statement just set.
     *
     * @return int Rows the statement affected
     */
    public function rowCount(): int
    {
        return $this->state->affectedRows();
    }

    /**
     * Answers the statement's result, reading it off the statement once.
     *
     * mysqli answers a statement's result exactly once; asking twice answers
     * false the second time. Everything that needs the result asks here, so it
     * is asked for once and kept.
     *
     * @return mysqli_result|false The result, or false where the statement has none
     */
    public function loadResult(): mysqli_result|false
    {
        if ($this->result === null) {
            $this->result = $this->statement->get_result();
        }

        return $this->result;
    }
}
