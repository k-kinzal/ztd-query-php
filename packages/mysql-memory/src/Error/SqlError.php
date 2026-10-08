<?php

declare(strict_types=1);

namespace MySqlMemory\Error;

use RuntimeException;
use Throwable;

/**
 * An error the server reports for a statement: the error number, the SQLSTATE and the message.
 *
 * @visibility public
 * @example Reading the error of a statement
 *     $session = (new \MySqlMemory\Instance())->connect();
 *     $session->query('SELECT * FROM nowhere') // throws \MySqlMemory\Error\SqlError: No database selected
 */
final class SqlError extends RuntimeException
{
    /**
     * @param ErrorCode $error The server error
     * @param string $text The message, with the arguments of the error filled in
     * @param Throwable|null $previous The failure the error reports, if any
     * @param list<array{int, string}> $following The further error conditions the server records after this one, each an error number and a message
     */
    public function __construct(public readonly ErrorCode $error, string $text, ?Throwable $previous = null, public readonly array $following = [])
    {
        parent::__construct($text, $error->value, $previous);
    }

    /**
     * Answers the five-character SQLSTATE of the error.
     */
    public function sqlState(): string
    {
        return $this->error->sqlState();
    }
}
