<?php

declare(strict_types=1);

namespace ZtdQuery\Platform;

use ZtdQuery\Schema\ColumnType;

/**
 * Encodes a PHP value as a dialect-specific, typed SQL expression.
 */
interface ValueRenderer
{
    public function renderValue(mixed $value, ?ColumnType $type = null): string;
}
