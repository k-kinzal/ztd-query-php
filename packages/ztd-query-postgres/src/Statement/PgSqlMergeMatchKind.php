<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres\Statement;

/**
 * The pg sql merge match kind a value can be.
 */
enum PgSqlMergeMatchKind: string
{
    case Matched = 'MATCHED';
    case NotMatched = 'NOT MATCHED';
}
