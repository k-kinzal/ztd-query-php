<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Contract for applying result rows to shadow state.
 *
 * @phpstan-import-type Row from StatementInterface
 */
interface ShadowMutation
{
    /**
     * Apply mutation to the given store.
     *
     * @param list<Row> $rows
     */
    public function apply(ShadowStore $store, array $rows): void;

    /**
     * Target table name for the mutation.
     */
    public function tableName(): string;
}
