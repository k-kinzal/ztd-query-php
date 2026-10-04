<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Session;

/**
 * What RESET and SHOW name with keywords instead of a parameter name.
 *
 * `TIME ZONE` is the parameter `timezone`, `TRANSACTION ISOLATION LEVEL` is
 * `transaction_isolation`, `SESSION AUTHORIZATION` is
 * `session_authorization`, and `ALL` is every parameter. The value is the
 * keyword spelling.
 * Source: https://www.postgresql.org/docs/17/sql-reset.html, https://www.postgresql.org/docs/17/sql-show.html.
 *
 * @visibility public
 * @example Reading the parameter a keyword form names
 *     \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SpecialParameter::TimeZone->parameter() // => 'timezone'
 */
enum SpecialParameter: string
{
    case All = 'ALL';
    case TimeZone = 'TIME ZONE';
    case TransactionIsolationLevel = 'TRANSACTION ISOLATION LEVEL';
    case SessionAuthorization = 'SESSION AUTHORIZATION';

    /**
     * Answers the name of the parameter the keywords stand for; null for ALL, which names every parameter.
     */
    public function parameter(): ?string
    {
        return match ($this) {
            self::All => null,
            self::TimeZone => 'timezone',
            self::TransactionIsolationLevel => 'transaction_isolation',
            self::SessionAuthorization => 'session_authorization',
        };
    }
}
