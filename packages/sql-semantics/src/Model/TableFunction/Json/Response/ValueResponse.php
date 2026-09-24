<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Json\Response;

/**
 * The requested response when a path is empty or its conversion fails.
 * @visibility public
 * @example Inspecting a value column response
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind("SELECT j.* FROM JSON_TABLE ('[]', '$[*]' COLUMNS (v INTEGER PATH '$.b' NULL ON EMPTY)) AS j");
 *     $statement->from->table->columns[0]->onEmpty instanceof \SqlSemantics\Model\TableFunction\Json\Response\ValueResponse // => true
 */
interface ValueResponse
{
}
