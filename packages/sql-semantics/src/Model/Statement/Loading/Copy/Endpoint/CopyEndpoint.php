<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Loading\Copy\Endpoint;

/**
 * Where COPY reads or writes data: a server file, a server program, or the client connection.
 * @visibility public
 * @example Reading the endpoint
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('COPY t FROM STDIN', strict: false);
 *     $statement->input instanceof \SqlSemantics\Model\Statement\Loading\Copy\Endpoint\CopyClient // => true
 */
interface CopyEndpoint
{
}
