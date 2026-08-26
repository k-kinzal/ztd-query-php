<?php

declare(strict_types=1);

namespace Tests\Fake;

use ZtdQuery\Sql\TransactionStatement;
use ZtdQuery\Sql\TransactionStatementParser;

/**
 * A parser that reads the transaction statements every dialect spells alike.
 *
 * Each dialect writes savepoints and the words that open a transaction a
 * little differently, which is why this is an interface. This reads the
 * spellings they agree on, so a test about the contract is not about a
 * dialect.
 */
final class FakeTransactionStatementParser implements TransactionStatementParser
{
    /**
     * Answers the transaction statement a statement is, if it is one.
     *
     * @param string $sql Statement as it was written
     *
     * @return TransactionStatement|null What it does to the transaction, or null when it is not one
     */
    public function parse(string $sql): ?TransactionStatement
    {
        $normalized = strtoupper(trim($sql, " \t\n\r;"));

        return match (true) {
            $normalized === 'BEGIN' => TransactionStatement::begin(),
            $normalized === 'COMMIT' => TransactionStatement::commit(),
            $normalized === 'ROLLBACK' => TransactionStatement::rollback(),
            str_starts_with($normalized, 'SAVEPOINT ') => TransactionStatement::savepoint(
                substr($normalized, strlen('SAVEPOINT ')),
            ),
            str_starts_with($normalized, 'ROLLBACK TO ') => TransactionStatement::rollbackTo(
                substr($normalized, strlen('ROLLBACK TO ')),
            ),
            str_starts_with($normalized, 'RELEASE ') => TransactionStatement::release(
                substr($normalized, strlen('RELEASE ')),
            ),
            default => null,
        };
    }
}
