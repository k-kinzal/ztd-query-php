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
     * Records a warning.
     */
    public function warning(ErrorCode $code, string $message): void
    {
        if (count($this->conditions) < 64) {
            $this->conditions[] = ['Warning', $code->value, $message];
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
     */
    public function error(int $code, string $message): void
    {
        $this->conditions[] = ['Error', $code, $message];
    }

    /**
     * Forgets every condition, as a new statement does.
     */
    public function clear(): void
    {
        $this->conditions = [];
    }

    /**
     * Counts the conditions.
     */
    public function count(): int
    {
        return count($this->conditions);
    }
}
