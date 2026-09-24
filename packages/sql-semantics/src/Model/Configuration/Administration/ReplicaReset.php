<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Administration;

use Override;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * RESET REPLICA (RESET SLAVE): forgets the replication position of one channel or of every channel, and with ALL also its connection settings.
 * @visibility public
 * @example Resetting one channel completely
 *     $reset = new \SqlSemantics\Model\Configuration\Administration\ReplicaReset(true, 'east');
 *     [$reset->all, $reset->channel] // => [true, 'east']
 */
final class ReplicaReset implements ResetTarget
{
    /**
     * A channel name cannot contain a line feed.
     * @throws InvalidStructure
     */
    public function __construct(public readonly bool $all = false, public readonly ?string $channel = null)
    {
        if ($channel !== null && str_contains($channel, "\n")) {
            throw new InvalidStructure('A replication channel name cannot contain a line feed.');
        }
    }

    /**
     * FOR CHANNEL exists from MySQL 5.7.
     */
    #[Override]
    public function availableIn(int $release): bool
    {
        return $this->channel === null || $release >= 50700;
    }
}
