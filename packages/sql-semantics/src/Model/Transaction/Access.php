<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Transaction;

/**

 * @visibility public
 * @example Reading the access mode of a transaction start
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $binder->bind('BEGIN READ ONLY')->characteristics->access // => \SqlSemantics\Model\Transaction\Access::ReadOnly
 *     $binder->bind('START TRANSACTION READ WRITE')->characteristics->access // => \SqlSemantics\Model\Transaction\Access::ReadWrite
 *     $binder->bind('BEGIN')->characteristics->access // => null

 */
enum Access: string
{
    case ReadOnly = 'READ ONLY';
    case ReadWrite = 'READ WRITE';
}
