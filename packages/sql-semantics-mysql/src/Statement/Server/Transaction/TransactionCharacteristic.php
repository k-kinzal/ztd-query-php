<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Transaction;

/**
 * A characteristic of START TRANSACTION.
 *
 * Mirrors the MYSQL_START_TRANS_OPT_* flags. Each case holds the keywords it
 * is written with. READ ONLY and READ WRITE exclude each other.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/commit.html.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Server\Transaction\TransactionCharacteristic::ReadOnly->value // => 'READ ONLY'
 */
enum TransactionCharacteristic: string
{
    case WithConsistentSnapshot = 'WITH CONSISTENT SNAPSHOT';
    case ReadOnly = 'READ ONLY';
    case ReadWrite = 'READ WRITE';
}
