<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Json\Response;

/**
 * A classified JSON_TABLE response for this operation.
 * @visibility public
 * @example Reading an existence error response
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind("SELECT j.* FROM JSON_TABLE ('[]', '$[*]' COLUMNS (ok BOOLEAN EXISTS PATH '$.a' FALSE ON ERROR)) AS j");
 *     $statement->from->table->columns[0]->onError // => \SqlSemantics\Model\TableFunction\Json\Response\ExistsResponse::False
 */
enum ExistsResponse: string
{
    case Default = '';
    case Error = 'ERROR';
    case True = 'TRUE';
    case False = 'FALSE';
    case Unknown = 'UNKNOWN';
}
