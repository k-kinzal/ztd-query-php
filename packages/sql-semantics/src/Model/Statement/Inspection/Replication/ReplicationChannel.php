<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection\Replication;

use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Checks the FOR CHANNEL operand of replication inspections.
 * @visibility SqlSemantics
 */
final class ReplicationChannel
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
        if ($origin->context?->schema()->grammarVersion === 'mysql-5.6.51') {
            throw new InvalidStructure('FOR CHANNEL requires MySQL 5.7 or later.');
        }
        if (str_contains($channel, "\n")) {
            throw new InvalidStructure('A replication channel name cannot contain a line feed.');
        }
    }
}
