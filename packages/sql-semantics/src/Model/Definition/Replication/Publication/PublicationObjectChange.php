<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Replication\Publication;

/**
 * How ALTER PUBLICATION changes the published objects.
 * @visibility public
 * @example Reading the change of a publication's objects
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER PUBLICATION pub DROP TABLES IN SCHEMA sales');
 *     $statement->change // => \SqlSemantics\Model\Definition\Replication\Publication\PublicationObjectChange::Drop
 */
enum PublicationObjectChange: string
{
    case Add = 'ADD';
    case Set = 'SET';
    case Drop = 'DROP';
}
