<?php

declare(strict_types=1);

namespace ZtdQuery\Platform;

use ZtdQuery\Schema\ColumnType;

interface ResultColumnTypeResolver
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function resolve(array $metadata): ColumnType;
}
