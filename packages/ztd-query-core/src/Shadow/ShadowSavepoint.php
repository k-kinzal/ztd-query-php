<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow;

use ZtdQuery\Schema\TableDefinitionRegistry;

/**
 * Everything a transaction would have to put back, as it was at one moment.
 *
 * Rolling back is not only about rows: a statement inside a transaction may
 * have created or dropped a table, and a rollback that restored the rows but
 * not what the tables are would leave the shadow describing a database that
 * never existed. Both are taken together, and put back together.
 */
final class ShadowSavepoint
{
    /**
     * Binds a savepoint to what it will put back.
     *
     * Both are taken as they are, which means a caller that means to remember
     * the shadow has to hand over copies of it; of() is what does that.
     *
     * @param string|null $name Name the savepoint was declared under, or null for the transaction itself
     * @param ShadowStore $store Rows as they were
     * @param TableDefinitionRegistry $registry Tables as they were
     */
    public function __construct(
        public readonly ?string $name,
        private readonly ShadowStore $store,
        private readonly TableDefinitionRegistry $registry,
    ) {
    }

    /**
     * Takes a savepoint of the shadow as it is now.
     *
     * @param string|null $name Name to declare it under, or null for the transaction itself
     * @param ShadowStore $store Rows to remember
     * @param TableDefinitionRegistry|null $registry Tables to remember, where anything is describing them
     *
     * @return self The savepoint
     */
    public static function of(?string $name, ShadowStore $store, ?TableDefinitionRegistry $registry): self
    {
        return new self(
            $name,
            $store->snapshot(),
            $registry?->snapshot() ?? new TableDefinitionRegistry(),
        );
    }

    /**
     * Puts the shadow back to what it was when the savepoint was taken.
     *
     * @param ShadowStore $store Rows to put back
     * @param TableDefinitionRegistry|null $registry Tables to put back, where anything is describing them
     */
    public function restoreInto(ShadowStore $store, ?TableDefinitionRegistry $registry): void
    {
        $store->restore($this->store);
        $registry?->restore($this->registry);
    }

    /**
     * Reports whether this is the savepoint declared under a name.
     *
     * @param string $name Name to test against
     *
     * @return bool True when it was declared under that name
     */
    public function isNamed(string $name): bool
    {
        return $this->name === $name;
    }
}
