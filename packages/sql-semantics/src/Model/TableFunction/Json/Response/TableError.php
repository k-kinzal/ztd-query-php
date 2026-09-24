<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Json\Response;

/**
 * A classified JSON_TABLE response for this operation.
 * @visibility public
 * @example Reading the table-level error response
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind("SELECT j.* FROM JSON_TABLE ('[]', '$[*]' COLUMNS (n FOR ORDINALITY) EMPTY ON ERROR) AS j");
 *     $statement->from->table->onError // => \SqlSemantics\Model\TableFunction\Json\Response\TableError::EmptyRows
 */
enum TableError: string
{
    case Default = '';
    case Error = 'ERROR';
    case EmptyRows = 'EMPTY';
}
