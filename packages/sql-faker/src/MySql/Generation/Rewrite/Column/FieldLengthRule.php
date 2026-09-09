<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite\Column;

use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * create_field.cc/Create_field::init checks display widths after the scanner accepts field_length.
 */
final class FieldLengthRule implements RewriteRule
{
    /**
     * Applies each type's width domain without changing ordinary numbers or YEAR's separate rule.
     */
    public function rewrite(TerminalSequence $sequence): TerminalSequence
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
