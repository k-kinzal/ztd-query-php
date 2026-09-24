<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Json;

use SqlSemantics\Model\Expression;

/**
 * An SQL/JSON input expression and its optional declared document format.
 * @visibility public
 * @example Reading the input document
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind("SELECT j.* FROM JSON_TABLE ('[]' FORMAT JSON, '$[*]' COLUMNS (n FOR ORDINALITY)) AS j");
 *     [$statement->from->table->document->expression->spelling(), $statement->from->table->document->format] // => ["'[]'", \SqlSemantics\Model\TableFunction\Json\Format::Json]
 */
final class Input
{
    /**
     * Describes how the consumer should interpret this expression as a JSON value.
     */
    public function __construct(public readonly Expression $expression, public readonly ?Format $format = null)
    {
    }
}
