<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Maintenance\MySql;

use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Statement\Origin;

/**
 * Derives the fixed result roles of table status and checksum operations.
 * @visibility SqlSemantics
 */
final class ResultColumns
{
    /**
     * @return list<OutputColumn> One column per status field; no runtime values
     */
    public static function status(Origin $origin): array
    {
        $columns = [];
        foreach (StatusField::cases() as $ordinal => $field) {
            $columns[] = new OutputColumn($ordinal, $field->value, new StatusColumn($origin->source, $origin->scopeId, $field));
        }
        return $columns;
    }

    /**
     * @return list<OutputColumn> The table name and nullable unsigned checksum
     */
    public static function checksum(Origin $origin): array
    {
        $columns = [];
        foreach (ChecksumField::cases() as $ordinal => $field) {
            $columns[] = new OutputColumn($ordinal, $field->value, new ChecksumColumn($origin->source, $origin->scopeId, $field));
        }
        return $columns;
    }
}
