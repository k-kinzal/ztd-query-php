<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Mysqli\Driver;

use mysqli_result;
use ZtdQuery\Connection\ResultColumn;
use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Platform\ResultColumnTypeResolver;

/**
 * @phpstan-import-type Row from StatementInterface
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
            /** @var Row $metadata */
            $metadata = get_object_vars($field);
            $type = $typeResolver->resolve($metadata);
            $columns[] = new ResultColumn($field->name, $type);
        }

        return $columns;
    }
}
