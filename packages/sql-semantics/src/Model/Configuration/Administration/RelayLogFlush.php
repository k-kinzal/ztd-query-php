<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Administration;

use Override;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * FLUSH RELAY LOGS, for one replication channel or for every channel when none is named.
 * @visibility public
 * @example Naming a channel
 *     (new \SqlSemantics\Model\Configuration\Administration\RelayLogFlush('east'))->channel // => 'east'
 */
final class RelayLogFlush implements FlushTarget
{
    /**
     * A channel name cannot contain a line feed.
     * @throws InvalidStructure
     */
    public function __construct(public readonly ?string $channel = null)
    {
        if ($channel !== null && str_contains($channel, "\n")) {
            throw new InvalidStructure('A replication channel name cannot contain a line feed.');
        }
    }

    /**
     * Relay logs exist in every release; FOR CHANNEL from MySQL 5.7.
     */
    #[Override]
    public function availableIn(int $release): bool
    {
        return $this->channel === null || $release >= 50700;
    }
}
