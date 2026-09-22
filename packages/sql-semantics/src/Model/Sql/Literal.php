<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Sql;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Encodes PHP scalar values as single SQL literals, never as SQL fragments.
 *
 * @visibility SqlSemantics
 */
final class Literal
{
    /**
     * @return array{string, string} Literal spelling and conservative SQL type
     * @throws InvalidStructure
     */
    public static function encode(string|int|float|bool|null $value, Dialect $dialect): array
    {
        if ($value === null) {
            return ['NULL', 'unknown'];
        }
        if (is_bool($value)) {
            return [$value ? 'TRUE' : 'FALSE', $dialect === Dialect::PostgreSql ? 'boolean' : 'integer'];
        }
        if (is_int($value)) {
            return [(string) $value, $dialect === Dialect::PostgreSql && ($value > 2147483647 || $value < -2147483648) ? 'bigint' : 'integer'];
        }
        if (is_float($value)) {
            if (!is_finite($value)) {
                throw new InvalidStructure('A numeric SQL literal must be finite.');
            }
            $text = json_encode($value, JSON_PRESERVE_ZERO_FRACTION);
            if ($text === false) {
                throw new InvalidStructure('The numeric value cannot be encoded as SQL.');
            }
            return [$text, $dialect === Dialect::Sqlite ? 'real' : ($dialect === Dialect::MySql && str_contains($text, 'e') ? 'double precision' : 'numeric')];
        }
        $text = $dialect === Dialect::MySql ? str_replace('\\', '\\\\', $value) : $value;
        return ["'" . str_replace("'", "''", $text) . "'", $dialect === Dialect::PostgreSql ? 'unknown' : 'text'];
    }
}
