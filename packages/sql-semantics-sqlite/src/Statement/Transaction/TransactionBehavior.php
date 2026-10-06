<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Transaction;

/**
 * When a transaction acquires its locks: the word written after BEGIN.
 *
 * Source: https://sqlite.org/lang_transaction.html#deferred_immediate_and_exclusive_transactions.
 *
 * @visibility public
 * @example Reading the behavior a transaction start requests
 *     $begin = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('BEGIN IMMEDIATE');
 *     $begin->statement->behavior // => \SqlSemantics\Platform\Sqlite\Statement\Transaction\TransactionBehavior::Immediate
 */
enum TransactionBehavior: string
{
    case Deferred = 'DEFERRED';
    case Immediate = 'IMMEDIATE';
    case Exclusive = 'EXCLUSIVE';
}
