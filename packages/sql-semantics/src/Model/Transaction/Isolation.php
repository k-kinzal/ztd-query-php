<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Transaction;

/**

 * @visibility public
 * @example Reading the isolation level of a transaction start
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $binder->bind('BEGIN ISOLATION LEVEL SERIALIZABLE')->characteristics->isolation // => \SqlSemantics\Model\Transaction\Isolation::Serializable
 *     $binder->bind('BEGIN')->characteristics->isolation // => null

 */
enum Isolation: string
{
    case ReadUncommitted = 'READ UNCOMMITTED';
    case ReadCommitted = 'READ COMMITTED';
    case RepeatableRead = 'REPEATABLE READ';
    case Serializable = 'SERIALIZABLE';
}
