<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Trigger;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Requires an unqualified PostgreSQL event-trigger identity.
 * @visibility SqlSemantics
 */
final class EventTriggerInvariant
{
    /**
     * Event triggers belong to the database rather than to a named relation.
     * @throws InvalidStructure
     */
    public static function target(Origin $origin, string $name): void
    {
        if ($origin->dialect !== Dialect::PostgreSql || $name === '') {
            throw new InvalidStructure('An event trigger requires PostgreSQL and a nonempty name.');
        }
    }
}
