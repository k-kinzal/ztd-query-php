<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Noise;

/**
 * The noise and synonym token positions of the data type productions.
 *
 * Only a token with no influence on meaning in its production may be listed
 * as noise, and only terminals the manual defines as synonyms may share a
 * key. Every entry is listed in the method documentation with its reason and
 * the manual page that states it.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TypeNoise
{
    /**
     * Answers the noise positions by production signature.
     *
     * - `real_type: DOUBLE_SYM PRECISION`, `opt_PRECISION: PRECISION`: DOUBLE
     *   PRECISION is DOUBLE
     *   (https://dev.mysql.com/doc/refman/8.4/en/numeric-type-syntax.html).
     *   As a cast target the model keeps the word (CastTarget), since it is
     *   part of the text MySQL names an unaliased select list expression after.
     * - the second and third keywords of the national type spellings: with the
     *   synonym key of position 0, `NATIONAL CHAR` is NCHAR and `NATIONAL
     *   VARCHAR`, `NCHAR VARCHAR`, `NATIONAL CHAR VARYING` and `NCHAR VARYING`
     *   are NVARCHAR (https://dev.mysql.com/doc/refman/8.4/en/string-type-syntax.html,
     *   https://dev.mysql.com/doc/refman/8.4/en/charset-national.html). As a
     *   cast target the model keeps `NATIONAL CHAR` (CastTarget).
     *
     * The optional INT after SIGNED and UNSIGNED of a cast target is kept by
     * the model (CastTarget) and is no noise.
     *
     * @return array<string, list<int>>
     */
    public static function positions(): array
    {
        return [
            'real_type: DOUBLE_SYM PRECISION' => [1],
            'opt_PRECISION: PRECISION' => [0],
            'nchar: NATIONAL_SYM CHAR_SYM' => [1],
            'nvarchar: NATIONAL_SYM VARCHAR' => [1],
            'nvarchar: NATIONAL_SYM VARCHAR_SYM' => [1],
            'nvarchar: NCHAR_SYM VARCHAR' => [1],
            'nvarchar: NCHAR_SYM VARCHAR_SYM' => [1],
            'nvarchar: NATIONAL_SYM CHAR_SYM VARYING' => [1, 2],
            'nvarchar: NCHAR_SYM VARYING' => [1],
        ];
    }

    /**
     * Answers the key of each synonym position by production signature.
     *
     * - the national type spellings: one key for NCHAR and one for NVARCHAR
     *   (https://dev.mysql.com/doc/refman/8.4/en/charset-national.html).
     * - NUMERIC and FIXED: synonyms of DECIMAL
     *   (https://dev.mysql.com/doc/refman/8.4/en/fixed-point-types.html,
     *   https://dev.mysql.com/doc/refman/8.4/en/numeric-type-syntax.html).
     * - BOOLEAN: a synonym of BOOL
     *   (https://dev.mysql.com/doc/refman/8.4/en/numeric-type-syntax.html).
     *
     * @return array<string, array<int, string>>
     */
    public static function synonyms(): array
    {
        return [
            'nchar: NATIONAL_SYM CHAR_SYM' => [0 => 'NCHAR_SYM'],
            'nvarchar: NATIONAL_SYM VARCHAR' => [0 => 'NVARCHAR_SYM'],
            'nvarchar: NATIONAL_SYM VARCHAR_SYM' => [0 => 'NVARCHAR_SYM'],
            'nvarchar: NCHAR_SYM VARCHAR' => [0 => 'NVARCHAR_SYM'],
            'nvarchar: NCHAR_SYM VARCHAR_SYM' => [0 => 'NVARCHAR_SYM'],
            'nvarchar: NATIONAL_SYM CHAR_SYM VARYING' => [0 => 'NVARCHAR_SYM'],
            'nvarchar: NCHAR_SYM VARYING' => [0 => 'NVARCHAR_SYM'],
            'type: NUMERIC_SYM float_options field_options' => [0 => 'DECIMAL_SYM'],
            'type: FIXED_SYM float_options field_options' => [0 => 'DECIMAL_SYM'],
            'numeric_type: NUMERIC_SYM' => [0 => 'DECIMAL_SYM'],
            'numeric_type: FIXED_SYM' => [0 => 'DECIMAL_SYM'],
            'type: BOOLEAN_SYM' => [0 => 'BOOL_SYM'],
        ];
    }
}
