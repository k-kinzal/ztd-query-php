<?php

declare(strict_types=1);

namespace MySqlMemory\Error;

/**
 * A server error number the emulator raises, with its SQLSTATE and message format.
 *
 * Each family of errors is an enum of the Family namespace backed by the error number: AccountError,
 * AdministrationError, ConstraintError, DataError, PartitionError, ProgramError, QueryError, SchemaError,
 * StatementError and TransactionError. ErrorNumbers finds the error of a number.
 * The formats are those of the server error reference; resources/errors.php holds them.
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 *
 * @visibility public
 * @example Building the error of a missing table
 *     $error = \MySqlMemory\Error\Family\QueryError::NoSuchTable->error('shop', 'items');
 *     [$error->getCode(), $error->sqlState(), $error->getMessage()] // => [1146, '42S02', "Table 'shop.items' doesn't exist"]
 */
interface ErrorCode
{
    /**
     * Answers the error number.
     */
    public function number(): int;

    /**
     * Answers the SQLSTATE of the error.
     */
    public function sqlState(): string;

    /**
     * Answers the message with the arguments filled into the format of the error.
     */
    public function message(string|int ...$arguments): string;

    /**
     * Answers the error with the arguments filled into its message.
     */
    public function error(string|int ...$arguments): SqlError;
}
