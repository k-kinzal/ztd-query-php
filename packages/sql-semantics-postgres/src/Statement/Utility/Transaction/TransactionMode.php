<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction;

/**
 * One characteristic a transaction is started with or set to.
 *
 * Mirrors the `transaction_mode_item` options of PostgreSQL's
 * `TransactionStmt`: the isolation level, the access mode and the deferrable
 * mode. The value is the keyword spelling. A list may repeat a
 * characteristic; the server applies the items in order, so the last one of
 * a characteristic is in effect.
 * Source: https://www.postgresql.org/docs/17/sql-set-transaction.html.
 *
 * @visibility public
 * @example Reading the parameter an item sets
 *     \SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\TransactionMode::ReadOnly->parameter() // => 'transaction_read_only'
 */
enum TransactionMode: string
{
    case ReadUncommitted = 'ISOLATION LEVEL READ UNCOMMITTED';
    case ReadCommitted = 'ISOLATION LEVEL READ COMMITTED';
    case RepeatableRead = 'ISOLATION LEVEL REPEATABLE READ';
    case Serializable = 'ISOLATION LEVEL SERIALIZABLE';
    case ReadOnly = 'READ ONLY';
    case ReadWrite = 'READ WRITE';
    case Deferrable = 'DEFERRABLE';
    case NotDeferrable = 'NOT DEFERRABLE';

    /**
     * Answers the name of the configuration parameter the item sets.
     */
    public function parameter(): string
    {
        return match ($this) {
            self::ReadUncommitted, self::ReadCommitted, self::RepeatableRead, self::Serializable => 'transaction_isolation',
            self::ReadOnly, self::ReadWrite => 'transaction_read_only',
            self::Deferrable, self::NotDeferrable => 'transaction_deferrable',
        };
    }
}
