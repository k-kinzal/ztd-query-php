<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Replication\Publication;

/**
 * Every table of the schema first in the search path when the command runs, left unresolved.
 * @visibility public
 * @example Reading the current schema of a publication
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE PUBLICATION pub FOR TABLES IN SCHEMA CURRENT_SCHEMA');
 *     $statement->objects[0] instanceof \SqlSemantics\Model\Definition\Replication\Publication\PublishedCurrentSchema // => true
 */
final class PublishedCurrentSchema implements PublicationMember
{
}
