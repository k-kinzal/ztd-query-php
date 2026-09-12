<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation\Row;

use ZtdQuery\Connection\ResultSet;
use ZtdQuery\Shadow\Mutation\ShadowMutation;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Mutation whose schema depends on the executed query's result metadata.
 */
interface ResultSetMutation extends ShadowMutation
{
    /**
     * Applies result set.
     *
     * @param ShadowStore $store
     * @param ResultSet $result
     */
    public function applyResultSet(ShadowStore $store, ResultSet $result): void;
}
