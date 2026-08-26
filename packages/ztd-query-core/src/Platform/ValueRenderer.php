<?php

declare(strict_types=1);

namespace ZtdQuery\Platform;

use ZtdQuery\Schema\ColumnDeclaration;

/**
 * Encodes a PHP value as a dialect-specific, typed SQL expression.
 */
interface ValueRenderer
{
    /**
     * Writes value.
     *
     * @param ?ColumnDeclaration $type
     * @return string
     */
    public function renderValue(mixed $value, ?ColumnDeclaration $type = null): string;
}
