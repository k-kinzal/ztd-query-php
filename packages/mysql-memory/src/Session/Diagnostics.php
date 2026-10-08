<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

use MySqlMemory\Error\ErrorCode;

/**
 * The diagnostics area of a session: the warnings and notes of the last statement that raised any.
 *
 * SHOW WARNINGS reads it; a statement that uses no table and raises nothing leaves it as it was.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-warnings.html.
 *
 * @visibility MySqlMemory
 */
final class Diagnostics
{
    /**
     * @var list<array{string, int, string}> The level, error number and message of each condition
     */
    public array $conditions = [];

    /**
     * @var array<int, array<string, string>> The condition information items a SIGNAL statement gave a condition, RETURNED_SQLSTATE among them, by the position of the condition
     */
    public array $signalled = [];

    /**
     * Records a warning.
     */
    public function warning(ErrorCode|int $code, string $message): void
    {
        if (count($this->conditions) < 64) {
            $this->conditions[] = ['Warning', $code instanceof ErrorCode ? $code->value : $code, $message];
        }
    }

    /**
     * Records a note.
     */
    public function note(ErrorCode $code, string $message): void
    {
        if (count($this->conditions) < 64) {
            $this->conditions[] = ['Note', $code->value, $message];
        }
    }

    /**
     * Records an error that ended the statement.
     *
     * @param array<string, string>|null $signalled The condition information items a SIGNAL statement gave the error, or null for an error the server raised
     */
    public function error(int $code, string $message, ?array $signalled = null): void
    {
        if ($signalled !== null) {
            $this->signalled[count($this->conditions)] = $signalled;
        }
        $this->conditions[] = ['Error', $code, $message];
    }

    /**
     * Records a warning a SIGNAL statement raises with its own condition information items.
     *
     * @param array<string, string> $signalled The items, RETURNED_SQLSTATE among them, by name
     */
    public function signal(int $code, string $message, array $signalled): void
    {
        if (count($this->conditions) < 64) {
            $this->signalled[count($this->conditions)] = $signalled;
            $this->conditions[] = ['Warning', $code, $message];
        }
    }

    /**
     * Answers a condition information item of the condition at a position, as GET DIAGNOSTICS reads it.
     *
     * A condition the server raises has the SQLSTATE of its error number and 'ISO 9075' for its
     * origins (verified on a live 8.4 server); one a SIGNAL statement raises has the items it set.
     * The items no condition sets are empty.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/get-diagnostics.html.
     */
    public function item(int $position, string $name): string
    {
        if (isset($this->signalled[$position])) {
            return $this->signalled[$position][$name] ?? '';
        }

        return match ($name) {
            'RETURNED_SQLSTATE' => ErrorCode::tryFrom($this->conditions[$position][1] ?? 0)?->sqlState() ?? 'HY000',
            'CLASS_ORIGIN', 'SUBCLASS_ORIGIN' => 'ISO 9075',
            default => '',
        };
    }

    /**
     * Forgets every condition, as a new statement does.
     */
    public function clear(): void
    {
        $this->conditions = [];
        $this->signalled = [];
    }

    /**
     * Counts the conditions.
     */
    public function count(): int
    {
        return count($this->conditions);
    }
}
