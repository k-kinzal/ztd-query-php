<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Mysqli;

/**
 * What ZTD needs to know about a connection after a statement has run.
 *
 * mysqli answers its properties untyped, and a connection that was never opened
 * answers nothing at all. This reads the three that decide what happens next —
 * whether the statement failed, what the driver said about it, and how many rows
 * it touched — and says what each of them is.
 */
final class ConnectionState
{
    /**
     * Binds the state to what answers the connection's properties.
     *
     * @param ConnectionProperties $properties What the connection answers about itself
     */
    public function __construct(private readonly ConnectionProperties $properties)
    {
    }

    /**
     * Answers the number the driver gave the last failure.
     *
     * @return int The driver's number for it, or 0 where nothing has failed
     */
    public function errorNumber(): int
    {
        $errorNumber = $this->properties->named('errno');

        return is_int($errorNumber) ? $errorNumber : 0;
    }

    /**
     * Answers what the driver said about the last failure.
     *
     * @return string The driver's message, or an empty string where it said nothing
     */
    public function errorMessage(): string
    {
        $message = $this->properties->named('error');

        return is_string($message) ? $message : '';
    }

    /**
     * Answers how many rows the last statement affected.
     *
     * mysqli answers this as a string where the count will not fit an int, and
     * as -1 where the statement failed; both are read here as the number they
     * stand for.
     *
     * @return int The number of rows
     */
    public function affectedRows(): int
    {
        $affectedRows = $this->properties->named('affected_rows');

        return is_int($affectedRows) || is_string($affectedRows) ? (int) $affectedRows : 0;
    }
}
