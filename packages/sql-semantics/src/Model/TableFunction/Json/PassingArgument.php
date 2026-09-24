<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Json;

/**
 * One named SQL/JSON path variable supplied by a PASSING clause.
 * @visibility public
 * @example Reading a path variable
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind("SELECT j.* FROM JSON_TABLE ('[]', '$[*]' PASSING 2 AS k COLUMNS (n FOR ORDINALITY)) AS j");
 *     [$statement->from->table->passing[0]->name, $statement->from->table->passing[0]->input->expression->spelling()] // => ['k', '2']
 */
final class PassingArgument
{
    /**
     * Associates the expression with a path-variable name without resolving its value.
     */
    public function __construct(public readonly string $name, public readonly Input $input)
    {
    }
}
