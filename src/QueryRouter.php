<?php

declare(strict_types=1);

namespace ZtdQuery;

use ZtdQuery\Simulator\StatementSimulator;
use PDOStatement;

/**
 * Normalizes query/prepare/exec handling for ZTD-aware PDO usage.
 */
final class QueryRouter
{
    /**
     * Session driving enable/disable decisions.
     *
     * @var ZteSession
     */
    private ZteSession $session;

    /**
     * Simulator used for exec() write paths.
     *
     * @var StatementSimulator
     */
    private StatementSimulator $simulator;

    /**
     * @param ZteSession $session Session state for enable/disable routing.
     * @param StatementSimulator $simulator Exec path simulator.
     */
    public function __construct(ZteSession $session, StatementSimulator $simulator)
    {
        $this->session = $session;
        $this->simulator = $simulator;
    }

    /**
     * Execute a query by delegating to prepare/execute to preserve behavior.
     *
     * @param callable $prepare
     * @param array<int|string, mixed> $fetchModeArgs
     * @phpstan-param callable(string): (PDOStatement|false) $prepare
     */
    public function query(string $query, callable $prepare, ?int $fetchMode, array $fetchModeArgs): PDOStatement|false
    {
        $statement = $prepare($query);
        if ($statement === false) {
            return false;
        }

        if ($fetchMode !== null) {
            $statement->setFetchMode($fetchMode, ...$fetchModeArgs);
        }

        $executed = $statement->execute();
        if ($executed === false) {
            return false;
        }

        return $statement;
    }

    /**
     * Execute a statement, using simulation when ZTD is enabled.
     *
     * @param callable $exec
     * @param callable $rawQuery
     * @phpstan-param callable(string): (int|false) $exec
     * @phpstan-param callable(string): (PDOStatement|false) $rawQuery
     */
    public function exec(string $statement, callable $exec, callable $rawQuery): int|false
    {
        if (!$this->session->isEnabled()) {
            return $exec($statement);
        }

        return $this->simulator->simulate($statement, $rawQuery);
    }

    /**
     * Prepare a statement via the provided PDO delegate.
     *
     * @param callable $prepare
     * @phpstan-param callable(string): (PDOStatement|false) $prepare
     */
    public function prepare(string $query, callable $prepare): PDOStatement|false
    {
        return $prepare($query);
    }
}
