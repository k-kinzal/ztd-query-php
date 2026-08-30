<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres\Statement;

/**
 * The pg sql table sample method a value can be.
 */
enum PgSqlTableSampleMethod: string
{
    case Bernoulli = 'BERNOULLI';
    case System = 'SYSTEM';
}
