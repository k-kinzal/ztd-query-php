<?php

declare(strict_types=1);

namespace Fuzz;

use mysqli;
use mysqli_result;
use mysqli_sql_exception;
use mysqli_stmt;

/**
 * A mysqli connection that fails the way the fuzz targets read failure.
 *
 * Every fuzz entry point puts mysqli into MYSQLI_REPORT_STRICT, under which a
 * statement the server refuses is raised rather than answered false. Nothing in
 * mysqli's own signatures says so, and nothing guarantees the mode stays on, so
 * a refusal that came back as false is raised here instead.
 */
final class ReportingMysqli
{
    /**
     * Binds the wrapper to the connection it speaks for.
     *
     * @param mysqli $connection Connection the statements run on
     */
    public function __construct(private readonly mysqli $connection)
    {
    }

    /**
     * Runs a statement and answers what the server sent back.
     *
     * @param string $sql Statement as it was written
     *
     * @return mysqli_result|true The result, or true where the statement has none
     *
     * @throws mysqli_sql_exception When the server refuses the statement
     */
    public function query(string $sql): mysqli_result|bool
    {
        $result = $this->connection->query($sql);
        if ($result === false) {
            throw $this->refusal();
        }

        return $result;
    }

    /**
     * Runs a statement with the parameters bound to it.
     *
     * @param string $sql Statement as it was written
     * @param list<mixed> $parameters Values to bind, in the order the statement writes them
     *
     * @return mysqli_result|true The result, or true where the statement has none
     *
     * @throws mysqli_sql_exception When the server refuses the statement
     */
    public function executeQuery(string $sql, array $parameters): mysqli_result|bool
    {
        $result = $this->connection->execute_query($sql, $parameters);
        if ($result === false) {
            throw $this->refusal();
        }

        return $result;
    }

    /**
     * Prepares a statement and closes it again, to see whether it reads.
     *
     * @param string $sql Statement as it was written
     *
     * @throws mysqli_sql_exception When the server refuses the statement
     */
    public function prepareAndClose(string $sql): void
    {
        $statement = $this->connection->prepare($sql);
        if (!$statement instanceof mysqli_stmt) {
            throw $this->refusal();
        }
        $statement->close();
    }

    /**
     * Answers the refusal the connection last reported, as an exception.
     *
     * @return mysqli_sql_exception What the server said about the last statement
     */
    public function refusal(): mysqli_sql_exception
    {
        return new mysqli_sql_exception($this->connection->error, $this->connection->errno);
    }
}
