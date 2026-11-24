<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Integrity;

use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Argument\NativePragmaInteger;

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
        if (str_starts_with($token, '-') || str_starts_with($token, '+')) {
            return null;
        }
        $value = (new NativePragmaInteger())->parse($token);

        return $value === 0 ? IntegrityLimit::DEFAULT_VALUE : $value;
    }
}
