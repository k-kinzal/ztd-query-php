<?php

declare(strict_types=1);

namespace ZtdQuery\Rewrite;

/**
 * Observable affected-row convention for a rewritten statement.
 */
enum AffectedRowsMode: string
{
    case None = 'none';
    case Changed = 'changed';
    case Matched = 'matched';
}
