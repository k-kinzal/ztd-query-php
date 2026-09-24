<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Replication\Publication;

/**
 * A kind of change a publication replicates, as named in its publish option.
 * @visibility public
 * @example Reading the published operations
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("CREATE PUBLICATION pub FOR ALL TABLES WITH (publish = 'insert, TRUNCATE')");
 *     $statement->options->publish // => [\SqlSemantics\Model\Definition\Replication\Publication\PublishedOperation::Insert, \SqlSemantics\Model\Definition\Replication\Publication\PublishedOperation::Truncate]
 */
enum PublishedOperation: string
{
    case Insert = 'insert';
    case Update = 'update';
    case Delete = 'delete';
    case Truncate = 'truncate';
}
