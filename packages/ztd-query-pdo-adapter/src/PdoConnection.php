<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo;

use PDO;
use PDOException;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Connection\StatementInterface;

/**
 * PDO adapter implementing ConnectionInterface for ZTD layer.
 *
 * This class wraps a PDO instance and provides the minimal interface
 * required by the ZTD session for executing queries.
 */
final class PdoConnection implements ConnectionInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * {@inheritDoc}
     *
     * @throws DatabaseException On database error when PDO is in exception mode.
     */
    public function query(string $sql): StatementInterface|false
    {
        try {
            $stmt = $this->pdo->query($sql);
            if ($stmt === false) {
                return false;
            }

            return new PdoStatement($stmt);
        } catch (PDOException $e) {
            throw new DatabaseException(
                $e->getMessage(),
                is_int($e->errorInfo[1] ?? null) ? $e->errorInfo[1] : null,
                (int) $e->getCode(),
                $e
            );
        }
    }

}
