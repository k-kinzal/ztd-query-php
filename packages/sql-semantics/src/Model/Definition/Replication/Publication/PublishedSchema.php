<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Replication\Publication;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Every table of a named schema, including tables created in it later.
 * @visibility public
 * @example Reading a published schema
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE PUBLICATION pub FOR TABLES IN SCHEMA sales, "Archive"');
 *     $schema = $statement->objects[1];
 *     $schema instanceof \SqlSemantics\Model\Definition\Replication\Publication\PublishedSchema ? $schema->name : null // => 'Archive'
 */
final class PublishedSchema implements PublicationMember
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $name)
    {
        if ($name === '') {
            throw new InvalidStructure('A published schema requires a nonempty name.');
        }
    }
}
