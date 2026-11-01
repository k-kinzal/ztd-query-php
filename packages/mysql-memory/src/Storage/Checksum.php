<?php

declare(strict_types=1);

namespace MySqlMemory\Storage;

use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Evaluation\ExactDecimal;
use SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\RowFormat;
use SqlSemantics\Platform\MySql\Statement\Table\Option\RowFormatOption;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

/**
 * Calculates CHECKSUM TABLE's unsigned sum of the CRC-32 of each record.
 *
 * The record starts with normalized NULL bits, followed by fields in declaration
 * order. MyISAM packs the high bits of a partial BIT field into the preamble after
 * the column's NULL bit (verified on a live 8.4 server). Virtual generated
 * columns have their NULL bits after stored columns, even when declared earlier.
 * Source: https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_table.cc,
 * https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/dd_table_share.cc.
 *
 * @visibility MySqlMemory
 */
final class Checksum
{
    /**
     * Sums the record checksums modulo 2^32, independently of row order.
     *
     * @param array<int, list<int|float|string|ExactDecimal|null>> $rows Stored rows
     */
    public function rows(TableDefinition $table, array $rows): string
    {
        $sum = 0;
        foreach ($rows as $row) {
            $sum = ($sum + crc32($this->record($table, $row))) % 4294967296;
        }
        return (string) $sum;
    }

    /**
     * Builds the bytes used by the server's extended checksum scan.
     *
     * @param list<int|float|string|ExactDecimal|null> $row One stored row
     */
    public function record(TableDefinition $table, array $row): string
    {
        $field = new ChecksumField();
        $bytes = $this->preamble($table, $row);
        foreach ($table->columns as $position => $column) {
            $bytes .= $field->value($column->domain, $row[$position] ?? null);
        }
        return $bytes;
    }

    /**
     * Encodes each column's NULL bit, then its MyISAM bit tail, setting unused bits to one.
     *
     * @param list<int|float|string|ExactDecimal|null> $row One stored row
     */
    public function preamble(TableDefinition $table, array $row): string
    {
        $bit = $this->packed($table) ? 0 : 1;
        $bits = $bit === 0 ? [] : [1];
        $stored = array_filter($table->columns, static fn ($column): bool => $column->generated === null || $column->stored);
        $virtual = array_diff_key($table->columns, $stored);
        foreach ($stored + $virtual as $position => $column) {
            $value = $row[$position] ?? null;
            if ($column->nullable()) {
                $bits[$bit++] = $value === null ? 1 : 0;
            }
            $tail = strcasecmp($table->engine, 'MyISAM') === 0 && $column->domain->field === Field::Bit ? $column->domain->length % 8 : 0;
            for ($offset = 0; $offset < $tail; $offset++) {
                $bits[$bit++] = $value === null ? 0 : (ord(((string) $value)[0]) >> $offset) & 1;
            }
        }
        $bytes = '';
        for ($at = 0; $at < $bit; $at += 8) {
            $byte = 0;
            for ($offset = 0; $offset < 8; $offset++) {
                $byte |= ($bits[$at + $offset] ?? 1) << $offset;
            }
            $bytes .= chr($byte);
        }
        return $bytes;
    }

    /**
     * Determines whether SQL record layout reserves a deleted-row bit.
     * This differs from InnoDB's effective physical ROW_FORMAT default.
     */
    public function packed(TableDefinition $table): bool
    {
        $format = RowFormat::Default;
        foreach ($table->statement->options ?? [] as $option) {
            if ($option instanceof RowFormatOption) {
                $format = $option->format;
            }
        }
        if ($format === RowFormat::Dynamic) {
            return true;
        }
        foreach ($table->columns as $column) {
            $field = $column->domain->field;
            if ($field->blob() || $field === Field::Geometry || $field === Field::Json || (($field === Field::VarChar || $field === Field::VarString) && $format !== RowFormat::Fixed)) {
                return true;
            }
        }
        return false;
    }
}
