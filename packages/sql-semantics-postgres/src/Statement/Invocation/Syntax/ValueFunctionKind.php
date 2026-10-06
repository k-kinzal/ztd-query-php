<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax;

use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

/**
 * The SQL-standard functions written as a bare keyword.
 *
 * Mirrors PostgreSQL's `SQLValueFunctionOp`, plus SYSTEM_USER, which the
 * server turns into a call of `pg_catalog.system_user()`. CURRENT_ROLE and
 * CURRENT_USER, and USER, are distinct spellings the server keeps apart.
 * Source: https://www.postgresql.org/docs/17/functions-datetime.html#FUNCTIONS-DATETIME-CURRENT,
 * https://www.postgresql.org/docs/17/functions-info.html#FUNCTIONS-INFO-SESSION.
 *
 * @visibility public
 * @example Reading the type of CURRENT_DATE
 *     \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\ValueFunctionKind::CurrentDate->builtin() // => \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Date
 */
enum ValueFunctionKind: string
{
    case CurrentDate = 'CURRENT_DATE';
    case CurrentTime = 'CURRENT_TIME';
    case CurrentTimestamp = 'CURRENT_TIMESTAMP';
    case Localtime = 'LOCALTIME';
    case Localtimestamp = 'LOCALTIMESTAMP';
    case CurrentRole = 'CURRENT_ROLE';
    case CurrentUser = 'CURRENT_USER';
    case SessionUser = 'SESSION_USER';
    case SystemUser = 'SYSTEM_USER';
    case User = 'USER';
    case CurrentCatalog = 'CURRENT_CATALOG';
    case CurrentSchema = 'CURRENT_SCHEMA';

    /**
     * Tells whether the function takes a fractional-seconds precision in parentheses.
     */
    public function precise(): bool
    {
        return match ($this) {
            self::CurrentTime, self::CurrentTimestamp, self::Localtime, self::Localtimestamp => true,
            self::CurrentDate, self::CurrentRole, self::CurrentUser, self::SessionUser, self::SystemUser, self::User, self::CurrentCatalog, self::CurrentSchema => false,
        };
    }

    /**
     * Answers the type of the result.
     */
    public function builtin(): Builtin
    {
        return match ($this) {
            self::CurrentDate => Builtin::Date,
            self::CurrentTime => Builtin::Timetz,
            self::CurrentTimestamp => Builtin::Timestamptz,
            self::Localtime => Builtin::Time,
            self::Localtimestamp => Builtin::Timestamp,
            self::SystemUser => Builtin::Text,
            self::CurrentRole, self::CurrentUser, self::SessionUser, self::User, self::CurrentCatalog, self::CurrentSchema => Builtin::Name,
        };
    }

    /**
     * Tells whether the result can be NULL: CURRENT_SCHEMA when no schema of the path exists, SYSTEM_USER for a session that was not authenticated.
     */
    public function nullable(): bool
    {
        return $this === self::CurrentSchema || $this === self::SystemUser;
    }
}
