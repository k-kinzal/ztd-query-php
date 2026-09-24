<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Plan;

/**
 * The selected MySqlFormat instruction.
 * @visibility public
 * @example Reading the requested plan format
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build());
 *     $binder->bind('EXPLAIN FORMAT=JSON SELECT 1')->options->format // => \SqlSemantics\Model\Plan\MySqlFormat::Json
 *     $binder->bind('EXPLAIN SELECT 1')->options->format // => \SqlSemantics\Model\Plan\MySqlFormat::Default
 */
enum MySqlFormat: string
{
    case Default = '';
    case Traditional = 'TRADITIONAL';
    case Json = 'JSON';
    case Tree = 'TREE';
}
