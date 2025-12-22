<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Shadow\ShadowStore;

/**
 * Contract for applying result rows to shadow state.
 */
interface ShadowMutation
{
    /**
     * Apply mutation to the given store.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function apply(ShadowStore $store, array $rows): void;

    /**
     * Target table name for the mutation.
     */
    public function tableName(): string;
}
