<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Json;

/**
 * A one-based ordinal within the current JSON path's row sequence.
 * @visibility public
 * @example Reading a JSON_TABLE ordinal column
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind("SELECT j.* FROM JSON_TABLE ('[]', '$[*]' COLUMNS (n FOR ORDINALITY)) AS j");
 *     $statement->from->table->columns[0]->name // => 'n'
 */
final class Ordinality implements Column
{
    /**
     * Declares a generated ordinal column, which has no value-path expression.
     */
    public function __construct(public readonly string $name)
    {
    }
}
