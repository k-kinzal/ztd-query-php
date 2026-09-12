<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Mysqli;

use mysqli_result;
use ZtdQuery\Connection\ResultColumn;
use ZtdQuery\Platform\ResultColumnTypeResolver;

/**
 * Converts native mysqli field metadata into platform-resolved result columns.
 */
final class MysqliResultColumnExtractor
{
    /**
     * @return list<ResultColumn>
     */
    public static function extract(mysqli_result $result, ResultColumnTypeResolver $typeResolver): array
    {
        $columns = [];
        foreach ($result->fetch_fields() as $field) {

            $metadata = [];
            foreach (get_object_vars($field) as $key => $value) {
                if (is_string($key)) {
                    $metadata[$key] = $value;
                }
            }
            $type = $typeResolver->resolve($metadata);
            $columns[] = new ResultColumn($field->name, $type);
        }

        return $columns;
    }
}
