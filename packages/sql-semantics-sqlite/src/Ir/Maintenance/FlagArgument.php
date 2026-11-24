<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance;

use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\PragmaKeyword;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\PragmaNumber;

/**
 * Applies SQLite's native flag-value interpretation to decoded pragma operands.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class FlagArgument
{
    /**
     * Resolves an optional argument to the requested change.
     */
    public function change(FlagName $name, PragmaNumber|Name|PragmaKeyword|null $value): ?FlagChange
    {
        if ($value === null) {
            return null;
        }
        $text = Comparison::AsciiInsensitive->fold($value instanceof PragmaNumber ? $value->nativeText() : $value->value);
        if ($name === FlagName::WritableSchema && $text === 'reset') {
            return FlagChange::Reset;
        }
        if (preg_match('/\A0x([0-9a-f]+)/', $text, $parts) === 1) {
            return $this->nonzero($parts[1], '7fffffff', 16) ? FlagChange::Enable : FlagChange::Disable;
        }
        if (preg_match('/\A[0-9]+/', $text, $parts) === 1) {
            return $this->nonzero($parts[0], '2147483647', 10) ? FlagChange::Enable : FlagChange::Disable;
        }

        return in_array($text, ['on', 'yes', 'true'], true) ? FlagChange::Enable : FlagChange::Disable;
    }

    /**
     * Tests the low byte of an unsigned digit prefix that fits the native signed integer bound.
     */
    public function nonzero(string $digits, string $maximum, int $base): bool
    {
        $digits = ltrim($digits, '0');
        $length = strlen($digits);
        $width = strlen($maximum);

        return ($length < $width || ($length === $width && strcmp($digits, $maximum) <= 0))
            && (intval($digits, $base) & 255) !== 0;
    }
}
