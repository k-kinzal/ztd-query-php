<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo\Session;

use Closure;
use PDOStatement;
use ZtdQuery\Adapter\Pdo\ZtdPdoException;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Session;

/**
 * Rewrites a prepared query against the current shadow before each execution.
 * The native preparation callback retains the caller's driver options.
 *
 * @visibility ZtdQuery\Adapter\Pdo
 */
final class PreparedQuery
{
    /**
     * @param Closure(string): (PDOStatement|false) $prepare Native preparation with its original options.
     */
    public function __construct(
        private readonly Session $session,
        private readonly string $sql,
        private readonly Closure $prepare,
    ) {
    }

    /**
     * Resolve a fresh plan after any preceding shadow mutations.
     *
     * @throws DatabaseException When the session rejects the query.
     */
    public function rewrite(): RewritePlan
    {
        return $this->session->rewrite($this->sql);
    }

    /**
     * Prepare the rewritten SQL using the caller's original driver options.
     *
     * @throws ZtdPdoException When a silent-mode driver refuses preparation.
     */
    public function prepare(string $sql): PDOStatement
    {
        $statement = ($this->prepare)($sql);
        if ($statement === false) {
            throw new ZtdPdoException('PDO failed to prepare rewritten SQL.');
        }
        return $statement;
    }
}
