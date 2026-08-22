<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Mysqli;

use mysqli_result;
use ZtdQuery\Connection\ResultColumn;
use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

final class MysqliResultColumnExtractor
{
    /** @return list<ResultColumn> */
    public static function extract(mysqli_result $result, ?ResultColumnTypeResolver $typeResolver = null): array
    {
        $columns = [];
        foreach ($result->fetch_fields() as $field) {
            /** @var array<string, mixed> $metadata */
            $metadata = get_object_vars($field);
            $type = $typeResolver?->resolve($metadata)
                ?? new ColumnType(ColumnTypeFamily::UNKNOWN, '');
            $columns[] = new ResultColumn($field->name, $type);
        }

        return $columns;
    }
}
