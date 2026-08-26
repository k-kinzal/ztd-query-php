<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow;

use ZtdQuery\Schema\TableDefinitionRegistry;

/**
 * Keeps the shadow at the point each open transaction could go back to.
 *
 * Nothing is sent to the database, so a transaction here is a stack of
 * savepoints over the shadow. A nested BEGIN is ignored, because that is what
 * a database does with one; a savepoint declared twice under the same name
 * takes the place of the first, which is what SQL says it does.
 */
final class ShadowTransactions
{
    /**
     * @var list<ShadowSavepoint> Points the shadow can be put back to, outermost first
     */
    private array $savepoints = [];

    /**
     * @param ShadowStore $store Rows a rollback puts back
     * @param TableDefinitionRegistry|null $registry Tables a rollback puts back, where anything is describing them
     */
    public function __construct(
        private readonly ShadowStore $store,
        private readonly ?TableDefinitionRegistry $registry = null,
    ) {
    }

    /**
     * Starts a transaction, unless one has already been started.
     */
    public function begin(): void
    {
        if ($this->savepoints !== []) {
            return;
        }

        $this->savepoints[] = ShadowSavepoint::of(null, $this->store, $this->registry);
    }

    /**
     * Keeps everything the transaction did.
     */
    public function commit(): void
    {
        $this->savepoints = [];
    }

    /**
     * Puts the shadow back to what it was when the transaction began.
     */
    public function rollBack(): void
    {
        if ($this->savepoints === []) {
            return;
        }

        $this->savepoints[0]->restoreInto($this->store, $this->registry);
        $this->savepoints = [];
    }

    /**
     * Declares a savepoint the transaction can go back to.
     *
     * @param string $name Name to declare it under
     */
    public function savepoint(string $name): void
    {
        $existing = $this->positionOf($name);
        if ($existing !== null) {
            $this->savepoints = array_slice($this->savepoints, 0, $existing);
        }
        $this->savepoints[] = ShadowSavepoint::of($name, $this->store, $this->registry);
    }

    /**
     * Puts the shadow back to a named savepoint, which stays declared.
     *
     * @param string $name Savepoint to go back to
     */
    public function rollBackTo(string $name): void
    {
        $index = $this->positionOf($name);
        if ($index === null) {
            return;
        }

        $this->savepoints[$index]->restoreInto($this->store, $this->registry);
        $this->savepoints = array_slice($this->savepoints, 0, $index + 1);
    }

    /**
     * Gives up a named savepoint, keeping everything done since it.
     *
     * @param string $name Savepoint to give up
     */
    public function release(string $name): void
    {
        $index = $this->positionOf($name);
        if ($index !== null) {
            $this->savepoints = array_slice($this->savepoints, 0, $index);
        }
    }

    /**
     * Answers where a named savepoint is, counting the innermost first.
     *
     * A name declared more than once names the innermost of them, which is the
     * one a rollback goes back to.
     *
     * @param string $name Savepoint to look for
     *
     * @return int|null Its position, or null when nothing is declared under that name
     */
    public function positionOf(string $name): ?int
    {
        for ($index = count($this->savepoints) - 1; $index >= 0; $index--) {
            if ($this->savepoints[$index]->isNamed($name)) {
                return $index;
            }
        }

        return null;
    }
}
