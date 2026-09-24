<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Json;

/**
 * A classified SQL/JSON format policy.
 * @visibility public
 * @example Reading a declared document format
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind("SELECT j.* FROM JSON_TABLE ('[]' FORMAT JSON ENCODING UTF8, '$[*]' COLUMNS (n FOR ORDINALITY)) AS j");
 *     $statement->from->table->document->format // => \SqlSemantics\Model\TableFunction\Json\Format::Utf8
 */
enum Format: string
{
    case Json = 'FORMAT JSON';
    case Utf8 = 'FORMAT JSON ENCODING UTF8';
    case Utf16 = 'FORMAT JSON ENCODING UTF16';
    case Utf32 = 'FORMAT JSON ENCODING UTF32';
}
