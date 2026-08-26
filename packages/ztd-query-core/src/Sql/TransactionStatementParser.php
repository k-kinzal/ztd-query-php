<?php

declare(strict_types=1);

namespace ZtdQuery\Sql;

/**
 * Reads a statement as what it does to a transaction, where that is what it is.
 *
 * Every dialect writes savepoints and the words that open a transaction a
 * little differently, so each database package reads its own.
 */
interface TransactionStatementParser
{
    /**
     * Reads.
     *
     * @param string $sql
     * @return ?TransactionStatement
     */
    public function parse(string $sql): ?TransactionStatement;
}
