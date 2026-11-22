<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Transaction;

/**
 * When a transaction acquires its locks: the word written after BEGIN.
 *
 * Source: https://sqlite.org/lang_transaction.html#deferred_immediate_and_exclusive_transactions.
 *
 * @visibility SqlSemantics
 */
enum TransactionBehavior: string
{
    case Deferred = 'DEFERRED';
    case Immediate = 'IMMEDIATE';
    case Exclusive = 'EXCLUSIVE';
}
