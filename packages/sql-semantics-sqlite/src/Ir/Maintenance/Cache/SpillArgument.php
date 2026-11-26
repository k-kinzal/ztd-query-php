<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache;

use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Argument\NativePragmaInteger;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;

/**
 * Interprets the native threshold and connection switch from a decoded operand.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class SpillArgument
{
    /**
     * Resolves the two related effects of a supplied spill argument.
     */
    public function read(string $text): SpillMode|SpillThreshold
    {
        $amount = (new NativePragmaInteger())->parse($text);
        $enabled = match (Comparison::AsciiInsensitive->fold($text)) {
            'on', 'yes', 'true' => true,
            'off', 'no', 'false' => false,
            default => preg_match('/\A[0-9]/', $text) === 1 ? (($amount ?? 0) & 255) !== 0 : $amount !== 0,
        };
        $mode = $enabled ? SpillMode::Enabled : SpillMode::Disabled;

        return $amount === null || $amount === 0 ? $mode : new SpillThreshold(new CacheAmount($amount), $mode);
    }
}
