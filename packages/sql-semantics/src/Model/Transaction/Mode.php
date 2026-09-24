<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Transaction;

/**

 * @visibility public
 * @example Reading the locking mode of a SQLite transaction start
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build());
 *     $binder->bind('BEGIN IMMEDIATE TRANSACTION')->mode // => \SqlSemantics\Model\Transaction\Mode::Immediate
 *     $binder->bind('BEGIN')->mode // => null

 */
enum Mode: string
{
    case Deferred = 'DEFERRED';
    case Immediate = 'IMMEDIATE';
    case Exclusive = 'EXCLUSIVE';
}
