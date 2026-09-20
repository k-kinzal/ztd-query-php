<?php

declare(strict_types=1);

namespace Fuzz\Shared\Database;

use Fuzz\Shared\Oracle\Finding;
use PDO;
use PDOException;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Connection\StatementInterface;

/**
 * Executes the platform's result-select SQL without PDO/MySQLi adapter behavior.
 */
final class Connection implements ConnectionInterface
{
    /**
     * Bridge a native connection directly to the core interface.
     */
    public function __construct(public readonly PDO $pdo)
    {
    }

    /**
     * Execute result-select SQL and reject silent native failures.
     * @throws Finding
     * @throws PDOException
     */
    public function query(string $sql): StatementInterface
    {
        try {
            $statement = $this->pdo->query($sql);
        } catch (PDOException $failure) {
            Infrastructure::check($failure);
            throw $failure;
        }
        if ($statement === false) {
            throw new Finding('Native query silently failed: ' . $sql);
        }
        return new Statement($statement);
    }
}
