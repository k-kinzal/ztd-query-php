<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow;

use ZtdQuery\Shadow\Mutation\ShadowMutation;

/**
 * Applies shadow mutations to the shadow store.
 */
final class ShadowApplier
{
    /**
     * Target shadow store receiving mutations.
     *
     * @var ShadowStore
     */
    private ShadowStore $store;

    /**
     * @param ShadowStore $store Target shadow store.
     */
    public function __construct(ShadowStore $store)
    {
        $this->store = $store;
    }

    /**
     * Apply a mutation using result rows.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function apply(ShadowMutation $mutation, array $rows): void
    {
        $mutation->apply($this->store, $rows);
    }
}
