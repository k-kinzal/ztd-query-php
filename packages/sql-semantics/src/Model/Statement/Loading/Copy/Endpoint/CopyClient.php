<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Loading\Copy\Endpoint;

/**
 * The client connection: STDIN for COPY FROM and STDOUT for COPY TO.
 * @visibility public
 * @example Writing to the client
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('COPY t TO STDIN', strict: false);
 *     [$statement->destination instanceof \SqlSemantics\Model\Statement\Loading\Copy\Endpoint\CopyClient, (new \SqlSemantics\SimpleSerializer())->serialize($statement)] // => [true, 'COPY "public"."t" TO STDOUT']
 */
final class CopyClient implements CopyEndpoint
{
}
