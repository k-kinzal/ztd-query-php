<?php

declare(strict_types=1);

namespace ZtdQuery\Rewrite;

use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Sql\TransactionStatement;

/**
 * Turns a statement into the plan ZTD will carry out instead of it.
 *
 * Every dialect reads its own SQL, so each database package implements this;
 * what the session does with the answer is the same whichever one it came
 * from. Two things an implementation may refuse are part of the contract
 * rather than accidents of it: a statement ZTD cannot simulate, and a table
 * nothing has told it about. The session decides what to do about each,
 * because what should happen is a matter of configuration.
 */
interface SqlRewriter
{
    /**
     * Answers the transaction statement a statement is, if it is one.
     *
     * @param string $sql Statement as it was written
     *
     * @return TransactionStatement|null What it does to the transaction, or null when it is not one
     */
    public function transactionStatement(string $sql): ?TransactionStatement;

    /**
     * Answers a SELECT this dialect accepts and that yields no rows.
     *
     * @return string The statement
     */
    public function emptyResultSelect(): string;

    /**
     * Splits a batch into the statements it is written as.
     *
     * Splitting is lexical: a semicolon inside a string, a comment or a
     * dollar-quoted body does not end a statement, and which of those exist is
     * a property of the dialect.
     *
     * @param string $sql Batch as it was written
     *
     * @return list<string> The statements, in the order they were written
     */
    public function splitStatements(string $sql): array;

    /**
     * Answers the plan for a statement, or for the first of a batch.
     *
     * @param string $sql Statement as it was written
     *
     * @return RewritePlan What ZTD will carry out instead of it
     *
     * @throws UnsupportedSqlException When ZTD cannot simulate the statement
     * @throws UnknownSchemaException When the statement names a table nothing has described
     */
    public function rewrite(string $sql): RewritePlan;

    /**
     * Answers a plan for each statement of a batch.
     *
     * @param string $sql Batch as it was written
     *
     * @return MultiRewritePlan What ZTD will carry out instead of each of them
     *
     * @throws UnsupportedSqlException When ZTD cannot simulate one of the statements
     * @throws UnknownSchemaException When one of them names a table nothing has described
     */
    public function rewriteMultiple(string $sql): MultiRewritePlan;
}
