<?php

declare(strict_types=1);

namespace ZtdQuery\Platform;

use ZtdQuery\Schema\ColumnDeclaration;

/**
 * Reads the type a driver reports for a result column.
 *
 * Every driver reports column metadata differently, and what ZTD needs from
 * it is the same everywhere: which family the column belongs to, so a value
 * read back can be rendered the way that column would hold it.
 */
interface ResultColumnTypeResolver
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function resolve(array $metadata): ColumnDeclaration;
}
