<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite\Column;

use SqlFaker\Generation\Token\RewriteRule;
use SqlFaker\Generation\Token\TerminalSequence;

/**
 * create_field.cc/Create_field::init checks display widths after the scanner accepts field_length.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/create_field.cc
 * @see https://github.com/mysql/mysql-server/blob/mysql-5.6.51/sql/field.cc
 * @see https://github.com/mysql/mysql-server/blob/mysql-5.7.44/sql/field.cc
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/field.cc
 */
final class FieldLengthRule implements RewriteRule
{
    /**
     * Applies each type's width domain without changing ordinary numbers or YEAR's separate rule.
     */
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        return $this->width($this->precision($sequence));
    }

    /**
     * Resolves precision and scale together so the digit count can depend on the selected scale.
     */
    public function precision(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('precision') as $id) {
            $range = $sequence->range($id);
            $type = $range === null ? null : $sequence->terminals[$range[0]]->ancestor('type');
            $owner = $type === null ? null : $sequence->range($type);
            if ($range === null || $owner === null || $range[1] - $range[0] !== 5) {
                continue;
            }
            $decimal = in_array($sequence->nameAt($owner[0]), ['DECIMAL_SYM', 'NUMERIC_SYM', 'FIXED_SYM'], true);
            foreach ([$range[0] + 1 => $decimal ? 'DECIMAL_DIGITS_NUMBER' : 'FLOAT_DIGITS_NUMBER', $range[0] + 3 => 'NUMERIC_SCALE_NUMBER'] as $index => $name) {
                if ($sequence->nameAt($index) !== $name) {
                    $sequence = $sequence->replace($index, 1, [
                        $sequence->terminals[$index]->replaced($name, 'sql/create_field.cc:precision-and-scale'),
                    ], 'sql/create_field.cc:precision-and-scale');
                }
            }
        }
        return $sequence;
    }

    /**
     * Resolves the single width argument using its owning column type.
     */
    public function width(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('field_length') as $id) {
            $range = $sequence->range($id);
            $type = $range === null ? null : $sequence->terminals[$range[0]]->ancestor('type');
            $owner = $type === null ? null : $sequence->range($type);
            if ($range === null || $owner === null || $sequence->nameAt($owner[0]) === 'YEAR_SYM') {
                continue;
            }
            $name = $sequence->nameAt($owner[0]);
            $domain = match ($name) {
                'INT_SYM', 'TINYINT_SYM', 'SMALLINT_SYM', 'MEDIUMINT_SYM', 'BIGINT_SYM',
                'CHAR_SYM', 'NCHAR_SYM', 'NATIONAL_SYM', 'BINARY_SYM' => 'DISPLAY_WIDTH_NUMBER',
                'BIT_SYM' => 'BIT_WIDTH_NUMBER',
                'DECIMAL_SYM', 'NUMERIC_SYM', 'FIXED_SYM' => 'DECIMAL_PRECISION_NUMBER',
                'FLOAT_SYM' => 'FLOAT_PRECISION_NUMBER',
                'VARCHAR_SYM', 'NVARCHAR_SYM', 'VARBINARY_SYM' => 'VARCHAR_LENGTH_NUMBER',
                default => 'FIELD_LENGTH_NUMBER',
            };
            if ($sequence->child($type, 'varchar') !== null || $sequence->child($type, 'nvarchar') !== null) {
                $domain = 'VARCHAR_LENGTH_NUMBER';
            }
            if ($sequence->nameAt($range[0] + 1) === $domain) {
                continue;
            }
            $sequence = $sequence->replace($range[0] + 1, 1, [
                $sequence->terminals[$range[0] + 1]->replaced($domain, 'sql/create_field.cc:Create_field::init'),
            ], 'sql/create_field.cc:Create_field::init');
        }
        return $sequence;
    }
}
