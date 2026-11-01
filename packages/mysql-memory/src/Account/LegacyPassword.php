<?php

declare(strict_types=1);

namespace MySqlMemory\Account;

/**
 * Computes the legacy sixteen-digit password value shared by SQL calls and account assignments.
 *
 * @visibility MySqlMemory
 */
final class LegacyPassword
{
    /**
     * Hashes nonempty text, ignoring spaces and tabs, and preserves the empty password.
     */
    public function hash(string $text): string
    {
        if ($text === '') {
            return '';
        }
        $first = 1345345333;
        $second = 0x12345671;
        $add = 7;
        foreach (str_split($text) as $character) {
            if ($character === ' ' || $character === "\t") {
                continue;
            }
            $byte = ord($character);
            $first = ($first ^ (((($first & 63) + $add) * $byte + ($first << 8)) & 0xFFFFFFFF)) & 0xFFFFFFFF;
            $second = ($second + ((($second << 8) & 0xFFFFFFFF) ^ $first)) & 0xFFFFFFFF;
            $add += $byte;
        }

        return sprintf('%08x%08x', $first & 0x7FFFFFFF, $second & 0x7FFFFFFF);
    }
}
