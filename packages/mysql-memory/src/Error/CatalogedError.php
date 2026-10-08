<?php

declare(strict_types=1);

namespace MySqlMemory\Error;

/**
 * The SQLSTATE, the message and the error of an error number, read from the error catalog.
 *
 * The enums of the error families use it; the error number is the backing value of the case.
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 *
 * @visibility MySqlMemory\Error
 */
trait CatalogedError
{
    /**
     * Answers the error number: the backing value of the case.
     *
     * @example A missing table
     *     \MySqlMemory\Error\QueryError::NoSuchTable->number() // => 1146
     */
    public function number(): int
    {
        return $this->value;
    }

    /**
     * Answers the SQLSTATE of the error.
     *
     * @example A duplicate key
     *     \MySqlMemory\Error\DataError::DuplicateEntry->sqlState() // => '23000'
     */
    public function sqlState(): string
    {
        return ErrorCatalog::instance()->entry($this->value)[0];
    }

    /**
     * Answers the message with the arguments filled into the format of the error.
     *
     * @example A missing column
     *     \MySqlMemory\Error\QueryError::BadField->message('a', 'field list') // => "Unknown column 'a' in 'field list'"
     */
    public function message(string|int ...$arguments): string
    {
        $format = ErrorCatalog::instance()->entry($this->value)[1];
        if (!str_contains($format, '%') && $arguments !== []) {
            return (string) $arguments[0];
        }

        return vsprintf($format, $arguments);
    }

    /**
     * Answers the error with the arguments filled into its message.
     *
     * @example No database selected
     *     \MySqlMemory\Error\QueryError::NoDatabase->error()->getMessage() // => 'No database selected'
     */
    public function error(string|int ...$arguments): SqlError
    {
        return new SqlError($this, $this->message(...$arguments));
    }
}
