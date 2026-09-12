<?php

declare(strict_types=1);

namespace ZtdQuery\Rewrite;

/**
 * Commits rewrite-time reservations only after simulated execution succeeds.
 */
interface RewriteStateCommitter
{
    /**
     * Commit rewrite state.
     *
     */
    public function commitRewriteState(): void;
}
