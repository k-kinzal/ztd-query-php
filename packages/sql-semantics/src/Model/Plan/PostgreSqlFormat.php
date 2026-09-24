<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Plan;

/**
 * The selected PostgreSqlFormat instruction.
 * @visibility public
 * @example Reading the requested plan format
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $binder->bind('EXPLAIN (FORMAT YAML) SELECT 1')->options->format // => \SqlSemantics\Model\Plan\PostgreSqlFormat::Yaml
 *     $binder->bind('EXPLAIN SELECT 1')->options->format // => \SqlSemantics\Model\Plan\PostgreSqlFormat::Text
 */
enum PostgreSqlFormat: string
{
    case Text = 'TEXT';
    case Json = 'JSON';
    case Xml = 'XML';
    case Yaml = 'YAML';
}
