<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Session;

/**
 * The settings SET writes with keywords of their own.
 *
 * Each stands for a parameter or a session property: SCHEMA sets
 * `search_path`, NAMES sets `client_encoding`, ROLE sets the current role,
 * SESSION AUTHORIZATION the session user, XML OPTION sets `xmloption`,
 * TRANSACTION SNAPSHOT imports a snapshot, and CATALOG would change the
 * database, which the server refuses. The value is the keyword spelling.
 * Source: https://www.postgresql.org/docs/17/sql-set.html, https://www.postgresql.org/docs/17/sql-set-role.html,
 * https://www.postgresql.org/docs/17/sql-set-session-authorization.html, https://www.postgresql.org/docs/17/sql-set-transaction.html.
 *
 * @visibility public
 * @example Reading the parameter SET SCHEMA changes
 *     \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SpecialSetting::Schema->parameter() // => 'search_path'
 */
enum SpecialSetting: string
{
    case Catalog = 'CATALOG';
    case Schema = 'SCHEMA';
    case Names = 'NAMES';
    case Role = 'ROLE';
    case SessionAuthorization = 'SESSION AUTHORIZATION';
    case XmlOption = 'XML OPTION';
    case TransactionSnapshot = 'TRANSACTION SNAPSHOT';

    /**
     * Answers the name of the configuration parameter the setting changes; null when it changes no parameter.
     */
    public function parameter(): ?string
    {
        return match ($this) {
            self::Catalog, self::TransactionSnapshot => null,
            self::Schema => 'search_path',
            self::Names => 'client_encoding',
            self::Role => 'role',
            self::SessionAuthorization => 'session_authorization',
            self::XmlOption => 'xmloption',
        };
    }
}
