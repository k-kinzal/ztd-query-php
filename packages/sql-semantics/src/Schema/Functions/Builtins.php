<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Functions;

use SqlSemantics\Dialect;
use SqlSemantics\Schema\FunctionSignature;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Registers default result facts through the same signatures as application functions.
 *
 * @visibility SqlSemantics
 */
final class Builtins
{
    /**
     * @return list<FunctionSignature>
     */
    public static function forDialect(Dialect $dialect): array
    {
        $groups = [
            'bigint' => ['COUNT', 'ROW_NUMBER', 'RANK', 'DENSE_RANK', 'NTILE'],
            'text' => ['LOWER', 'UPPER', 'TRIM', 'LTRIM', 'RTRIM', 'CONCAT', 'CONCAT_WS', 'SUBSTR', 'SUBSTRING', 'REPLACE', 'STRING_AGG', 'GROUP_CONCAT'],
            'integer' => ['LENGTH', 'CHAR_LENGTH', 'CHARACTER_LENGTH'],
            'double precision' => ['TOTAL', 'RAND', 'PERCENT_RANK', 'CUME_DIST'],
            'boolean' => ['BOOL_AND', 'BOOL_OR', 'EVERY'],
            'json' => ['JSON_AGG'],
            'jsonb' => ['JSONB_AGG'],
            'date' => ['CURRENT_DATE'],
            'timestamp' => ['CURRENT_TIMESTAMP', 'NOW'],
            'unknown' => ['RANDOM'],
            'argument' => ['GENERATE_SERIES', 'UNNEST', 'MIN', 'MAX', 'ABS', 'ROUND', 'LAG', 'LEAD', 'FIRST_VALUE', 'LAST_VALUE', 'NTH_VALUE', 'AVG', 'SUM', 'ARRAY_AGG'],
        ];
        $result = [];
        foreach ($groups as $type => $names) {
            foreach ($names as $name) {
                $aggregate = in_array($name, ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX', 'TOTAL', 'GROUP_CONCAT', 'STRING_AGG', 'ARRAY_AGG', 'JSON_AGG', 'JSONB_AGG', 'BOOL_AND', 'BOOL_OR', 'EVERY'], true);
                $notNull = in_array($name, ['COUNT', 'ROW_NUMBER', 'RANK', 'DENSE_RANK', 'NTILE', 'CURRENT_DATE', 'CURRENT_TIMESTAMP', 'RANDOM', 'RAND', 'TOTAL'], true);
                $strict = in_array($name, ['LOWER', 'UPPER', 'LENGTH', 'CHAR_LENGTH', 'ABS', 'ROUND', 'TRIM', 'LTRIM', 'RTRIM'], true);
                $return = $type === 'argument' ? (new BuiltinResult($name, $dialect))->resolve(...) : TypeDescriptor::builtin($dialect, $type === 'bigint' && $dialect === Dialect::Sqlite ? 'integer' : $type);
                $result[] = new FunctionSignature(strtolower($name), null, $return, $notNull || $strict ? Nullability::NotNull : ($aggregate ? Nullability::MaybeNull : Nullability::Unknown), $strict, aggregate: $aggregate);
            }
        }
        return $result;
    }
}
