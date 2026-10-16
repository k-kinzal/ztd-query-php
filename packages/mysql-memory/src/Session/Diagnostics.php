<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Error\ErrorNumbers;
use MySqlMemory\Error\SqlError;

/**
 * The diagnostics area of a session: the warnings and notes of the last statement that raised any.
 *
 * SHOW WARNINGS reads it; a statement that uses no table and raises nothing leaves it as it was.
 * It keeps at most max_error_count conditions, and counts those beyond (verified on a
 * live 8.4 server).
 * In MySQL 5.6 a query, SET or DO that uses no table keeps it until the statement raises a
 * condition (verified on a live 5.6.51 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-warnings.html,
 * https://dev.mysql.com/doc/refman/5.6/en/show-warnings.html.
 *
 * @visibility MySqlMemory
 */
final class Diagnostics
{
    /**
     * The sequence of SQL condition replacements, including conditions consumed by handlers.
     * Isolating a stored-program diagnostics frame does not advance it.
     */
    public int $revision = 0;

    /**
     * Executed SIGNAL statements, used to invalidate retained conditions across routine calls.
     * Ordinary area clearing and handled conditions do not reset this sequence.
     */
    public int $signalCount = 0;

    /**
     * @var list<array{string, int, string}> The level, error number and message of each condition
     */
    public array $conditions = [];

    /**
     * @var array<int, array<string, string>> The condition information items a SIGNAL statement gave a condition, RETURNED_SQLSTATE among them, by the position of the condition
     */
    public array $signalled = [];

    /**
     * Whether the conditions are those of an earlier statement, forgotten when the current statement raises one.
     */
    public bool $stale = false;

    /**
     * The most conditions the area keeps: max_error_count.
     */
    public int $limit = 1024;

    /**
     * The conditions raised beyond the limit, which the area counts without keeping.
     */
    public int $dropped = 0;

    /**
     * Errors included in dropped, counted by @@error_count even when no condition is stored.
     */
    public int $droppedErrors = 0;

    /**
     * The error handled by a stored program, independently of bounded condition storage.
     */
    public ?SqlError $failure = null;

    /**
     * @var array{int, int} The conditions and the errors the statement before the current one raised, which @@warning_count and @@error_count read
     */
    public array $previous = [0, 0];

    /**
     * Records a warning.
     */
    public function warning(ErrorCode|int $code, string $message): void
    {
        $this->fresh();
        if (count($this->conditions) < $this->limit) {
            $this->conditions[] = ['Warning', $code instanceof ErrorCode ? $code->number() : $code, $message];
        } else {
            $this->dropped++;
        }
    }

    /**
     * Records a note.
     */
    public function note(ErrorCode $code, string $message): void
    {
        $this->fresh();
        if (count($this->conditions) < $this->limit) {
            $this->conditions[] = ['Note', $code->number(), $message];
        } else {
            $this->dropped++;
        }
    }

    /**
     * Records an error that ended the statement.
     *
     * @param array<string, string>|null $signalled The condition information items a SIGNAL statement gave the error, or null for an error the server raised
     */
    public function error(int $code, string $message, ?array $signalled = null): void
    {
        $this->fresh();
        if (count($this->conditions) >= $this->limit) {
            $this->dropped++;
            $this->droppedErrors++;

            return;
        }
        if ($signalled !== null) {
            $this->signalled[count($this->conditions)] = $signalled;
        }
        $this->conditions[] = ['Error', $code, \MySqlMemory\Value\Diagnostic\Encoding::repair($message)];
    }

    /**
     * Records a warning a SIGNAL statement raises with its own condition information items.
     *
     * @param array<string, string> $signalled The items, RETURNED_SQLSTATE among them, by name
     */
    public function signal(int $code, string $message, array $signalled): void
    {
        $this->fresh();
        if (count($this->conditions) < $this->limit) {
            $this->signalled[count($this->conditions)] = $signalled;
            $this->conditions[] = ['Warning', $code, $message];
        } else {
            $this->dropped++;
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
            'RETURNED_SQLSTATE' => ErrorNumbers::tryFrom($this->conditions[$position][1] ?? 0)?->sqlState() ?? 'HY000',
            'CLASS_ORIGIN', 'SUBCLASS_ORIGIN' => 'ISO 9075',
            default => '',
        };
    }

    /**
     * Forgets every condition, as a new statement does.
     *
     * @param bool $observable Whether this is a SQL reset rather than an isolated frame's initialization
     */
    public function clear(bool $observable = true): void
    {
        if ($observable) {
            $this->revision++;
        }
        $this->conditions = [];
        $this->signalled = [];
        $this->stale = false;
        $this->dropped = 0;
        $this->droppedErrors = 0;
        $this->failure = null;
    }

    /**
     * Keeps the conditions of the earlier statement until the current one raises a condition.
     */
    public function retain(): void
    {
        $this->stale = true;
    }

    /**
     * Forgets the conditions of an earlier statement kept by retain().
     */
    public function fresh(): void
    {
        $this->revision++;
        if ($this->stale) {
            $this->clear();
        }
    }

    /**
     * Discards oldest stored conditions to reserve the requested number of slots for RESIGNAL.
     */
    public function reserve(int $slots = 1): void
    {
        $remove = max(0, count($this->conditions) - $this->limit + $slots);
        if ($remove === 0 || $this->limit === 0) {
            return;
        }
        $this->conditions = array_slice($this->conditions, $remove);
        $items = [];
        foreach ($this->signalled as $position => $values) {
            if ($position >= $remove) {
                $items[$position - $remove] = $values;
            }
        }
        $this->signalled = $items;
    }

    /**
     * Counts the conditions.
     */
    public function count(): int
    {
        return count($this->conditions);
    }

    /**
     * Counts the conditions raised, those beyond the limit included.
     */
    public function raised(): int
    {
        return count($this->conditions) + $this->dropped;
    }

    /**
     * Counts the errors raised, including those not stored because of max_error_count.
     */
    public function errors(): int
    {
        return count(array_filter($this->conditions, static fn (array $condition): bool => $condition[0] === 'Error')) + $this->droppedErrors;
    }
}
