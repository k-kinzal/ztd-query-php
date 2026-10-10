<?php

declare(strict_types=1);

namespace MySqlMemory\Typing;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Value\Encoding;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

/**
 * Produces binary, Unicode code-point, general_ci and single-byte latin1 sort weights.
 *
 * The general_ci character mapping was sampled through SQL for every valid BMP code point.
 * Supplementary characters weigh FFFD. Padding follows the collation; the binary charset
 * pads with zero bytes only when flag 128 requests it. Verified on MySQL 8.4.7.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-functions.html#function_weight-string.
 *
 * @visibility MySqlMemory
 */
final class Weights
{
    /**
     * @var array<int, string>|null The nonidentity pages of general_ci code-point weights
     */
    private static ?array $general = null;

    /**
     * Weighs a string, limiting or padding its character count when one is requested.
     *
     * @throws \MySqlMemory\Error\SqlError When the collation's weights are not emulated
     */
    public function text(string $text, Collation $collation, int $count = 0, int $flags = 0, bool $pad = true): string
    {
        if ($collation->charset === Charset::binary()) {
            $bytes = $count > 0 ? substr($text, 0, $count) : $text;

            return ($flags & 128) !== 0 ? str_pad($bytes, $count, "\0") : $bytes;
        }
        $characters = $text === '' ? [] : mb_str_split(Encoding::convert($text, $collation->charset, Charset::known('utf8mb4')), 1, 'UTF-8');
        if ($count > 0) {
            $characters = array_slice($characters, 0, $count);
            if ($pad && $collation->padSpace) {
                $characters = array_pad($characters, $count, ' ');
            }
        }
        $weight = '';
        foreach ($characters as $character) {
            $weight .= $this->character($character, $collation);
        }

        return $weight;
    }

    /**
     * Answers the weight of one UTF-8 character under a supported collation.
     *
     * @throws \MySqlMemory\Error\SqlError When the collation's weights are not emulated
     */
    public function character(string $character, Collation $collation): string
    {
        $single = Ordering::weights()[$collation->name] ?? null;
        if ($single !== null) {
            $byte = Encoding::convert($character, Charset::known('utf8mb4'), $collation->charset);

            return $single[ord($byte)];
        }
        $point = mb_ord($character, 'UTF-8');
        if ($collation->name === 'utf8mb4_0900_bin') {
            return $character;
        }
        if (in_array($collation->charset->name, ['utf8mb3', 'utf8mb4', 'ucs2', 'utf16', 'utf16le', 'utf32'], true)) {
            if ($collation->binaryOrder()) {
                return substr(pack('N', $point), in_array($collation->charset->name, ['utf8mb3', 'ucs2'], true) ? 2 : 1);
            }
            if (str_ends_with($collation->name, '_general_ci')) {
                return $this->general($point);
            }
        }
        throw StatementError::NotSupportedYet->error('WEIGHT_STRING of the collation ' . $collation->name);
    }

    /**
     * Answers the two-byte general_ci weight of a Unicode code point.
     */
    public function general(int $point): string
    {
        if ($point > 65535) {
            return "\xff\xfd";
        }
        if (self::$general === null) {
            /** @var array<int, string> $pages */
            $pages = require dirname(__DIR__, 2) . '/resources/general-ci-weights.php';
            self::$general = array_map(static fn (string $hex): string => (string) hex2bin($hex), $pages);
        }
        $page = self::$general[$point >> 8] ?? null;

        return $page === null ? pack('n', $point) : substr($page, ($point & 255) * 2, 2);
    }
}
