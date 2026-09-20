<?php

declare(strict_types=1);

namespace Fuzz\Shared\Database;

use Fuzz\Shared\Input\Command;
use Fuzz\Shared\Oracle\Finding;
use PDOException;
use ZtdQuery\Session;

/**
 * Drives the core contract directly; production PDO and MySQLi adapters are not involved.
 */
final class SessionExecutor
{
    /**
     * Attach a core session to the independent native connection bridge.
     */
    public function __construct(public readonly Session $session, private readonly Connection $connection)
    {
    }

    /**
     * @return array<int, array<string, mixed>>|int
      * @throws Finding
     * @throws PDOException
     * @throws \ZtdQuery\Connection\Exception\DatabaseException
     */
    public function execute(Command $command): array|int
    {
        if ($command->kind === 'transaction') {
            $transaction = $this->session->transactionStatement($command->sql);
            if ($transaction === null) {
                throw new Finding('Transaction was not recognized: ' . $command->sql);
            }
            $this->session->applyTransactionStatement($transaction);
            return 0;
        }
        if ($command->kind !== 'read') {
            $count = $this->session->execStatement($command->sql);
            if ($count === false) {
                throw new Finding('Session silently rejected a statement: ' . $command->sql);
            }
            return $count;
        }
        $plan = $this->session->rewrite($command->sql);
        $statement = $this->connection->query($plan->sql());
        return $this->session->processExecutedStatement($plan, $statement)->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
      * @throws Finding
     * @throws PDOException
     * @throws \ZtdQuery\Connection\Exception\DatabaseException
     */
    public function rows(string $sql): array
    {
        $result = $this->execute(new Command($sql));
        if (!is_array($result)) {
            throw new Finding('A SELECT did not return rows.');
        }
        return $result;
    }
}
