<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Integrity;

/**
 * Interprets the native numeric token of an integrity report budget.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class IntegrityNumber
{
    /**
     * Reads the same unsigned integer prefix as sqlite3GetInt32 on a checked numeric token.
     */
    public function limit(string $token): ?int
    {
        $hexadecimal = strncasecmp($token, '0x', 2) === 0;
        $pattern = $hexadecimal ? '/\A0[xX]([0-9a-fA-F]+)/' : '/\A([0-9]+)/';
        if (preg_match($pattern, $token, $prefix) !== 1) {
            return null;
        }

        $digits = strtolower(ltrim($prefix[1], '0'));
        $maximum = $hexadecimal ? '7fffffff' : (string) IntegrityLimit::MAX_VALUE;
        if (strlen($digits) > strlen($maximum) || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0)) {
            return null;
        }

        return $digits === '' ? IntegrityLimit::DEFAULT_VALUE : intval($digits, $hexadecimal ? 16 : 10);
    }
}
