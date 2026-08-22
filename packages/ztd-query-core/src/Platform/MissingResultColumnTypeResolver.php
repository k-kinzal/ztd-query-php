<?php

declare(strict_types=1);

namespace ZtdQuery\Platform;

use LogicException;
use ZtdQuery\Schema\ColumnType;

final class MissingResultColumnTypeResolver implements ResultColumnTypeResolver
{
    public function resolve(array $metadata): ColumnType
    {
        throw new LogicException('A database platform result column type resolver is required.');
    }
}
