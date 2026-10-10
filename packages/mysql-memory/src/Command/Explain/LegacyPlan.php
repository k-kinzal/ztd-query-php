<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Explain;

use MySqlMemory\Command\Show\Heading;
use MySqlMemory\Result\ColumnFlag;
use SqlSemantics\Platform\MySql\Statement\Dml\Delete;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainModifier;

/**
 * The traditional EXPLAIN layouts of MySQL 5.x. In 5.6, EXTENDED adds filtered and PARTITIONS
 * adds partitions; plain EXPLAIN has ten columns. Unrestricted DELETE reports its
 * deletion shortcut instead of a table scan. MySQL 5.7 retains twelve columns, the DML kind
 * and table name, with the older numeric widths. Observed through MySQL 5.6.51 and 5.7.44.
 * Source: https://dev.mysql.com/doc/refman/5.6/en/explain-extended.html.
 *
 * @visibility MySqlMemory
 */
final class LegacyPlan
{
    /**
     * Narrows the modern layout to the columns selected by the legacy modifier.
     *
     * @param list<Heading> $headings
     * @return list<Heading>
     */
    public static function headings(array $headings, ?ExplainModifier $modifier, string $version = '5.6.51'): array
    {
        $flags = ColumnFlag::Binary->value | ColumnFlag::Numeric->value | ColumnFlag::Unsigned->value;
        $headings[0] = new Heading('id', Field::LongLong, 3, ColumnFlag::NotNull->value | $flags);
        $headings[9] = new Heading('rows', Field::LongLong, 10, $flags);
        if (str_starts_with($version, '5.7.')) {
            return array_values($headings);
        }
        if ($modifier !== ExplainModifier::Partitions) {
            unset($headings[3]);
        }
        if ($modifier !== ExplainModifier::Extended) {
            unset($headings[10]);
        }

        return array_values($headings);
    }

    /**
     * Projects legacy rows using the same modifier as their headings.
     *
     * @param list<list<int|string|null>> $rows
     * @return list<list<int|string|null>>
     */
    public static function rows(array $rows, Explain $explain, string $version = '5.6.51'): array
    {
        foreach ($rows as &$row) {
            if ($explain->statement instanceof Delete && $explain->statement->where === null && $explain->statement->limit === null) {
                $row[11] = 'Deleting all rows';
                if (str_starts_with($version, '5.6.')) {
                    $row = [1, 'SIMPLE', null, null, null, null, null, null, null, $row[9], null, 'Deleting all rows'];
                }
            }
            if (str_starts_with($version, '5.7.')) {
                $row = array_values($row);
                continue;
            }
            $row[1] = 'SIMPLE';
            if ($explain->modifier !== ExplainModifier::Partitions) {
                unset($row[3]);
            }
            if ($explain->modifier !== ExplainModifier::Extended) {
                unset($row[10]);
            }
            $row = array_values($row);
        }

        return $rows;
    }
}
