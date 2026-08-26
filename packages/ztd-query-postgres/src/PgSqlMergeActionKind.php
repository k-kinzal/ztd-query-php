<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres;

/**
 * The pg sql merge action kind a value can be.
 */
enum PgSqlMergeActionKind: string
{
    case Update = 'UPDATE';
    case Insert = 'INSERT';
    case Delete = 'DELETE';
    case DoNothing = 'DO NOTHING';
}
