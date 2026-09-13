<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres;

/**
 * Enumerates the row actions supported by MERGE simulation.
 */
enum PgSqlMergeActionKind: string
{
    case Update = 'UPDATE';
    case Insert = 'INSERT';
    case Delete = 'DELETE';
    case DoNothing = 'DO NOTHING';
}
