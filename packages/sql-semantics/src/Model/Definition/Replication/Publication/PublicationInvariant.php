<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Replication\Publication;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * The publication identity and object-list rules PostgreSQL applies within one command.
 * @visibility SqlSemantics
 */
final class PublicationInvariant
{
    /**
     * A publication is a PostgreSQL object with a nonempty name.
     * @throws InvalidStructure
     */
    public static function identity(Origin $origin, string $name): void
    {
        if ($origin->dialect !== Dialect::PostgreSql || $name === '') {
            throw new InvalidStructure('A publication requires PostgreSQL and a nonempty name.');
        }
    }

    /**
     * A table listed twice has neither columns nor a filter, and column lists cannot accompany a published schema.
     * @param list<PublicationMember> $objects
     * @return non-empty-list<PublicationMember>
     * @throws InvalidStructure
     */
    public static function objects(array $objects): array
    {
        Collections::objects($objects, PublicationMember::class);
        $tables = array_values(array_filter($objects, static fn (PublicationMember $object): bool => $object instanceof PublishedTable));
        $refined = array_filter($tables, static fn (PublishedTable $table): bool => $table->columns !== [] || $table->filter !== null);
        $names = array_map(static fn (PublishedTable $table): string => implode('.', $table->table->name->parts), $tables);
        foreach ($refined as $table) {
            if (count(array_keys($names, implode('.', $table->table->name->parts), true)) > 1) {
                throw new InvalidStructure('A table listed more than once cannot have a column list or row filter.');
            }
        }
        if (count($tables) !== count($objects) && array_filter($tables, static fn (PublishedTable $table): bool => $table->columns !== []) !== []) {
            throw new InvalidStructure('Column lists cannot be combined with TABLES IN SCHEMA.');
        }
        return Collections::nonEmpty($objects);
    }
}
