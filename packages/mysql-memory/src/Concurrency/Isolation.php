<?php

declare(strict_types=1);

namespace MySqlMemory\Concurrency;

use SqlSemantics\Platform\MySql\Statement\Utility\Set\IsolationLevel;

/**
 * A transaction isolation level, as the transaction_isolation variable names it.
 *
 * The variable takes the name of a level in any letter case, with hyphens and without
 * surrounding spaces, or its number from 0 (READ-UNCOMMITTED) to 3 (SERIALIZABLE), and holds the
 * name in upper case (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/innodb-transaction-isolation-levels.html,
 * https://dev.mysql.com/doc/refman/8.4/en/server-system-variables.html#sysvar_transaction_isolation.
 *
 * @visibility MySqlMemory
 */
enum Isolation: string
{
    case ReadUncommitted = 'READ-UNCOMMITTED';
    case ReadCommitted = 'READ-COMMITTED';
    case RepeatableRead = 'REPEATABLE-READ';
    case Serializable = 'SERIALIZABLE';

    /**
     * Answers the level a value of transaction_isolation names, or null when it names none.
     */
    public static function named(string $text): ?self
    {
        if (preg_match('/\A[0-3]\z/', $text) === 1) {
            return self::cases()[(int) $text];
        }

        return self::tryFrom(strtoupper($text));
    }

    /**
     * Answers the level SET TRANSACTION ISOLATION LEVEL names.
     */
    public static function of(IsolationLevel $level): self
    {
        return match ($level) {
            IsolationLevel::ReadUncommitted => self::ReadUncommitted,
            IsolationLevel::ReadCommitted => self::ReadCommitted,
            IsolationLevel::RepeatableRead => self::RepeatableRead,
            IsolationLevel::Serializable => self::Serializable,
        };
    }
}
