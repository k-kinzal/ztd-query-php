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
     * @param array<string, string>|null $signalled The condition information items a SIGNAL statement gives the condition, RETURNED_SQLSTATE among them, by name; null for an error the server raises
     * @param int|null $number The error number a SIGNAL statement gives the condition, or null for that of the error
     */
    public function __construct(public readonly ErrorCode $error, string $text, ?Throwable $previous = null, public readonly array $following = [], public readonly ?array $signalled = null, ?int $number = null)
    {
        parent::__construct($text, $number ?? $error->value, $previous);
    }

    /**
     * Answers the five-character SQLSTATE of the error.
     */
    public function sqlState(): string
    {
        return $this->signalled['RETURNED_SQLSTATE'] ?? $this->error->sqlState();
    }
}
