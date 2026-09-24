<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Replication\Publication;

/**
 * A table or schema whose changes a publication replicates.
 * @visibility public
 * @example Reading the objects of a publication
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE PUBLICATION pub FOR TABLE t, TABLES IN SCHEMA sales');
 *     $statement->objects[0] instanceof \SqlSemantics\Model\Definition\Replication\Publication\PublicationMember // => true
 *     $statement->objects[1] instanceof \SqlSemantics\Model\Definition\Replication\Publication\PublishedSchema // => true
 */
interface PublicationMember
{
}
