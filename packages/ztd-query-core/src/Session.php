<?php

declare(strict_types=1);

namespace ZtdQuery;

use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Schema\ViewDefinitionSet;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Shadow\ShadowTransactions;
use ZtdQuery\Sql\TransactionStatement;
use ZtdQuery\Sql\TransactionTarget;

/**
 * Owns virtual rows, schema, transactions, and connection-local ZTD state.
 *
 * A session does not reflect a database, rewrite SQL, or execute statements.
 * @visibility public
 * @example Keep fixtures isolated from another session
 *     $first = new \ZtdQuery\Session();
 *     $second = new \ZtdQuery\Session();
 *     $first->store()->set('items', [['id' => 1]]);
 *     $second->store()->get('items') // => []
 *     $first->isEnabled() // => true
 */
final class Session
{
    private bool $enabled = true;
    private ?string $lastInsertId = null;
    private readonly ShadowTransactions $transactions;

    /**
     * Start an isolated session, optionally with caller-provided fixtures and schema.
     */
    public function __construct(
        private readonly ShadowStore $store = new ShadowStore(),
        private readonly TableDefinitionRegistry $registry = new TableDefinitionRegistry(),
        private readonly ViewDefinitionSet $views = new ViewDefinitionSet(),
    ) {
        $this->transactions = new ShadowTransactions($store, $registry);
    }

    /**
     * Return the virtual row store.
     */
    public function store(): ShadowStore
    {
        return $this->store;
    }

    /**
     * Return the virtual table catalog.
     */
    public function registry(): TableDefinitionRegistry
    {
        return $this->registry;
    }

    /**
     * Return the reflected view definitions.
     */
    public function views(): ViewDefinitionSet
    {
        return $this->views;
    }

    /**
     * Remember a generated identity, retaining the previous one for other writes.
     */
    public function rememberInsertId(?string $identity): void
    {
        $this->lastInsertId = $identity ?? $this->lastInsertId;
    }

    /**
     * Enable ZTD behavior for this session.
     */
    public function enable(): void
    {
        $this->enabled = true;
    }

    /**
     * Disable ZTD behavior for this session.
     */
    public function disable(): void
    {
        $this->enabled = false;
    }

    /**
     * Check whether ZTD mode is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Provides the target used to apply transaction statements to shadow state.
     */
    public function transactions(): TransactionTarget
    {
        return $this->transactions;
    }

    /**
     * Starts a transaction over the shadow state.
     */
    public function beginTransaction(): void
    {
        $this->transactions->begin();
    }

    /**
     * Keeps the current shadow state and discards transactional snapshots.
     */
    public function commitTransaction(): void
    {
        $this->transactions->commit();
    }

    /**
     * Restores the shadow state captured when the transaction began.
     */
    public function rollBackTransaction(): void
    {
        $this->transactions->rollBack();
    }

    /**
     * Applies a parsed transaction statement to this session's shadow state.
     */
    public function applyTransactionStatement(TransactionStatement $statement): void
    {
        $statement->apply($this->transactions);
    }

    /**
     * Answers the identity the last simulated insert would have been given.
     *
     * @return string|false The identity, or false when nothing has been inserted
     */
    public function lastInsertId(): string|false
    {
        return $this->lastInsertId ?? false;
    }

    /**
     * Answers what a table was described as, where something described it.
     *
     * @param string $tableName Table to answer for
     *
     * @return TableDefinition|null Its description, or null when nothing has described it
     */
    public function tableDefinition(string $tableName): ?TableDefinition
    {
        return $this->registry->get($tableName);
    }
}
