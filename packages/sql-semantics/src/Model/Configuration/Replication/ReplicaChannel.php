<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Replication;

use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Checks the FOR CHANNEL operand of replication commands.
 * @visibility SqlSemantics
 */
final class ReplicaChannel
{
    /**
     * Channels exist from MySQL 5.7, and a channel name cannot contain a line feed.
     * @throws InvalidStructure
     */
    public static function check(Origin $origin, ?string $channel): void
    {
        if ($channel === null) {
            return;
        }
        ReplicationRelease::require($origin, 'FOR CHANNEL', 50700);
        if (str_contains($channel, "\n")) {
            throw new InvalidStructure('A replication channel name cannot contain a line feed.');
        }
    }

}
