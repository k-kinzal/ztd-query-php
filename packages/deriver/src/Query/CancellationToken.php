<?php

declare(strict_types=1);

namespace Deriver\Query;

/**
 * An explicit caller-controlled request to stop further analysis work.
 *
 * Cancellation is permanent for this token. Create a new token for a later operation.
 * The analyzer observes it at instruction and callable boundaries without running callbacks.
 *
 * @visibility public
 * @example Requesting cancellation
 *     $token = new \Deriver\Query\CancellationToken();
 *     $token->cancel();
 *     $token->isRequested() // => true
 */
final class CancellationToken
{
    /**
     * Whether the caller requested cancellation.
     */
    private bool $requested = false;

    /**
     * Requests that the active or next query stop and preserve unexplored behavior.
     */
    public function cancel(): void
    {
        $this->requested = true;
    }

    /**
     * Returns the current cancellation request without changing it.
     * @return bool Whether further analysis work should stop
     */
    public function isRequested(): bool
    {
        return $this->requested;
    }
}
