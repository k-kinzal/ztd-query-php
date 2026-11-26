<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation;

/**
 * Whose values an INSERT writes into identity columns: OVERRIDING USER VALUE or OVERRIDING SYSTEM VALUE.
 *
 * Mirrors PostgreSQL's `OverridingKind` (`OVERRIDING_USER_VALUE`,
 * `OVERRIDING_SYSTEM_VALUE`); the value is the keyword between OVERRIDING and VALUE.
 * Source: https://www.postgresql.org/docs/17/sql-insert.html.
 *
 * @visibility SqlSemantics
 */
enum Overriding: string
{
    case User = 'USER';
    case System = 'SYSTEM';
}
