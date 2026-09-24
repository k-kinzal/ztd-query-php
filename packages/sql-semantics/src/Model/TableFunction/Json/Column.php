<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Json;

/**
 * A classified JSON_TABLE column declaration, including nested row expansion.
 * @visibility public
 * @example Inspecting a column declaration
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind("SELECT j.* FROM JSON_TABLE ('[]', '$[*]' COLUMNS (n FOR ORDINALITY)) AS j");
 *     $statement->from->table->columns[0] instanceof \SqlSemantics\Model\TableFunction\Json\Column // => true
 */
interface Column
{
}
