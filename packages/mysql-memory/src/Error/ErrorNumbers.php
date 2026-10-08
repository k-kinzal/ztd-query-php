<?php

declare(strict_types=1);

namespace MySqlMemory\Error;

use ValueError;

/**
 * Finds the server error of an error number among the families of errors.
 *
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 *
 * @visibility public
 * @example Finding the error of a number
 *     \MySqlMemory\Error\ErrorNumbers::tryFrom(1146) // => \MySqlMemory\Error\QueryError::NoSuchTable
 */
final class ErrorNumbers
{
    /**
     * The enums of the families of errors, each backed by the error number.
     */
    public const FAMILIES = [AccountError::class, AdministrationError::class, DataError::class, ProgramError::class, QueryError::class, SchemaError::class, StatementError::class];

    /**
     * Answers the error of a number, or null when the emulator does not raise that number.
     */
    public static function tryFrom(int $number): ?ErrorCode
    {
        foreach (self::FAMILIES as $family) {
            $code = $family::tryFrom($number);
            if ($code !== null) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Answers the error of a number the emulator raises.
     *
     * @throws ValueError When the emulator does not raise that number
     */
    public static function from(int $number): ErrorCode
    {
        return self::tryFrom($number) ?? throw new ValueError(sprintf('%d is not a valid backing value for enum %s', $number, ErrorCode::class));
    }
}
