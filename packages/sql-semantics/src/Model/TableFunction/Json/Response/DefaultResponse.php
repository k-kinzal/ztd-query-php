<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Json\Response;

use SqlSemantics\Model\Expression;

/**
 * A default expression evaluated by the consumer when a JSON path cannot supply a value.
 * @visibility public
 * @example Reading a default expression
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind("SELECT j.* FROM JSON_TABLE ('[]', '$[*]' COLUMNS (v INTEGER PATH '$.b' DEFAULT 0 ON EMPTY)) AS j");
 *     $statement->from->table->columns[0]->onEmpty->expression->spelling() // => '0'
 */
final class DefaultResponse implements ValueResponse
{
    /**
     * Keeps the required default expression without evaluating it.
     */
    public function __construct(public readonly Expression $expression)
    {
    }
}
