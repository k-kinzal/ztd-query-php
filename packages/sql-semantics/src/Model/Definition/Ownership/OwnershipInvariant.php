<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Ownership;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Requires an explicit nonempty PostgreSQL owner selection.
 * @visibility SqlSemantics
 */
final class OwnershipInvariant
{
    /**
     * Rejects invalid dialects, lists, and role alternatives at construction time.
     * @param non-empty-list<NamedRole|SessionRole> $owners Ownership selection
     * @throws InvalidStructure
     */
    public static function owners(Origin $origin, array $owners): void
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('Owned-object selection requires PostgreSQL.');
        }
        Collections::alternatives(Collections::nonEmpty($owners), [NamedRole::class, SessionRole::class]);
    }
}
