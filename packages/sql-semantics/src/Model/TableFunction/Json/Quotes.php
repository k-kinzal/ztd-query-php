<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Json;

/**
 * A classified SQL/JSON quotes policy.
 * @visibility public
 * @example Reading a quotes policy
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind("SELECT j.* FROM JSON_TABLE ('[]', '$[*]' COLUMNS (v INTEGER PATH '$.b' OMIT QUOTES)) AS j");
 *     $statement->from->table->columns[0]->quotes // => \SqlSemantics\Model\TableFunction\Json\Quotes::Omit
 */
enum Quotes: string
{
    case Default = '';
    case Keep = 'KEEP QUOTES';
    case Omit = 'OMIT QUOTES';
}
