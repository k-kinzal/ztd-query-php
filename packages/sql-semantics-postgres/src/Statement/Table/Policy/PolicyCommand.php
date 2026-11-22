<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Policy;

/**
 * The commands a row security policy applies to.
 *
 * Mirrors the `cmd_name` of `CreatePolicyStmt`. ALL is the default and is kept when written.
 * Source: https://www.postgresql.org/docs/17/sql-createpolicy.html.
 *
 * @visibility SqlSemantics
 */
enum PolicyCommand: string
{
    case All = 'ALL';
    case Select = 'SELECT';
    case Insert = 'INSERT';
    case Update = 'UPDATE';
    case Delete = 'DELETE';
}
