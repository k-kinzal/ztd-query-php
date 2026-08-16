<?php

declare(strict_types=1);

namespace ZtdQuery\Rewrite;

/**
 * Native UPDATE row-count convention for a database connection.
 */
enum AffectedRowsMode: string
{
    case Changed = 'changed';
    case Matched = 'matched';
}
