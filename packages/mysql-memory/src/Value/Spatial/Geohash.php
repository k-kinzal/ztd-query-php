<?php

declare(strict_types=1);

namespace MySqlMemory\Value\Spatial;

/**
 * Encodes interleaved longitude and latitude intervals as base-32 geohashes.
 * Decoding selects the shortest decimal rounding of the cell center that stays
 * inside the cell. Encoding stops when the cell center exactly represents both
 * coordinates, or the requested length is reached. Verified against native SQL.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/spatial-geohash-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Geohash
{
    /**
     * The case-insensitive base-32 alphabet in increasing digit order.
     */
    public const ALPHABET = '0123456789bcdefghjkmnpqrstuvwxyz';

    /**
     * Encodes coordinates whose ranges and requested length the caller validated.
     */
    public static function encode(float $longitude, float $latitude, int $length): string
    {
        $values = [$longitude, $latitude];
        $lower = [-180.0, -90.0];
        $upper = [180.0, 90.0];
        $axis = 0;
        $result = '';
        for ($index = 0; $index < $length; $index++) {
            $digit = 0;
            for ($bit = 0; $bit < 5; $bit++) {
                $middle = ($lower[$axis] + $upper[$axis]) / 2;
                $high = !($values[$axis] < $middle);
                $digit = ($digit << 1) | (int) $high;
                if ($high) {
                    $lower[$axis] = $middle;
                } else {
                    $upper[$axis] = $middle;
                }
                $axis = 1 - $axis;
            }
            $result .= self::ALPHABET[$digit];
            if (($lower[0] + $upper[0]) / 2 === $longitude && ($lower[1] + $upper[1]) / 2 === $latitude) {
                break;
            }
        }
        return $result;
    }

    /**
     * Decodes at most 433 characters, validating every character in that prefix.
     *
     * @return array{float, float}|null Longitude and latitude, or an invalid encoding
     */
    public static function decode(string $value): ?array
    {
        if ($value === '') {
            return null;
        }
        $lower = [-180.0, -90.0];
        $upper = [180.0, 90.0];
        $axis = 0;
        foreach (str_split(strtolower(substr($value, 0, 433))) as $character) {
            $digit = strpos(self::ALPHABET, $character);
            if ($digit === false) {
                return null;
            }
            for ($bit = 4; $bit >= 0; $bit--) {
                $middle = ($lower[$axis] + $upper[$axis]) / 2;
                if (($digit & (1 << $bit)) !== 0) {
                    $lower[$axis] = $middle;
                } else {
                    $upper[$axis] = $middle;
                }
                $axis = 1 - $axis;
            }
        }
        return [self::rounded($lower[0], $upper[0]), self::rounded($lower[1], $upper[1])];
    }

    /**
     * Rounds a cell center without moving the decoded coordinate outside its bounds.
     */
    public static function rounded(float $lower, float $upper): float
    {
        $middle = ($lower + $upper) / 2;
        for ($places = 0; $places <= 324; $places++) {
            $rounded = round($middle, $places, PHP_ROUND_HALF_EVEN);
            if ($rounded >= $lower && $rounded <= $upper) {
                return $rounded;
            }
        }
        return $middle;
    }
}
