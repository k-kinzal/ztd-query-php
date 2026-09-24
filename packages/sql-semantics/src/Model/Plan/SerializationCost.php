<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Plan;

/**
 * The selected SerializationCost instruction.
 * @visibility public
 * @example Reading the requested serialization measurement
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $binder->bind('EXPLAIN (ANALYZE, SERIALIZE BINARY) SELECT 1')->options->serialization // => \SqlSemantics\Model\Plan\SerializationCost::Binary
 *     $binder->bind('EXPLAIN (ANALYZE) SELECT 1')->options->serialization // => \SqlSemantics\Model\Plan\SerializationCost::None
 */
enum SerializationCost: string
{
    case None = 'NONE';
    case Text = 'TEXT';
    case Binary = 'BINARY';
}
