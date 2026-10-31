<?php

declare(strict_types=1);

namespace MySqlMemory\Value\Spatial;

/**
 * Reads the axis order of an OGC spatial reference definition and exchanges geometry coordinates.
 *
 * Geographic storage uses longitude followed by latitude. Projected and unspecified
 * systems retain their stored order when exported with an axis-order option.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/spatial-reference-systems.html.
 *
 * @visibility MySqlMemory
 */
final class Axes
{
    /**
     * Answers whether a geographic definition puts latitude first, or NULL for a nongeographic system.
     */
    public static function latitudeFirst(string $definition): ?bool
    {
        preg_match_all('/"[^"]*"|[a-z_]+|[\[\]()]/i', $definition, $matched);
        $tokens = $matched[0];
        if (strtoupper($tokens[0] ?? '') !== 'GEOGCS') {
            return null;
        }
        $depth = 0;
        foreach ($tokens as $index => $token) {
            if ($depth === 1 && strcasecmp($token, 'AXIS') === 0) {
                return in_array(strtoupper($tokens[$index + 3] ?? ''), ['NORTH', 'SOUTH'], true);
            }
            $depth += in_array($token, ['[', '('], true) ? 1 : (in_array($token, [']', ')'], true) ? -1 : 0);
        }

        return false;
    }

    /**
     * Reads the top-level geographic angular unit in radians per coordinate unit.
     */
    public static function angularUnit(string $definition): float
    {
        return self::angle($definition, 'UNIT', 0.017453292519943278);
    }

    /**
     * Reads the geographic prime meridian in the reference system's angular units.
     */
    public static function primeMeridian(string $definition): float
    {
        return self::angle($definition, 'PRIMEM', 0.0);
    }

    /**
     * Reads a top-level numeric coordinate attribute, ignoring nested and quoted names.
     */
    public static function angle(string $definition, string $attribute, float $default): float
    {
        preg_match_all('/"[^"]*"|[a-z_]+|[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:e[+-]?[0-9]+)?|[\[\]()]/i', $definition, $matched);
        $depth = 0;
        foreach ($matched[0] as $index => $token) {
            if ($depth === 1 && strcasecmp($token, $attribute) === 0) {
                return (float) ($matched[0][$index + 3] ?? $default);
            }
            $depth += in_array($token, ['[', '('], true) ? 1 : (in_array($token, [']', ')'], true) ? -1 : 0);
        }
        return $default;
    }

    /**
     * Exchanges every coordinate pair, retaining component order and the reference identifier.
     */
    public static function swap(Geometry $geometry): Geometry
    {
        return new Geometry($geometry->type, array_map(static fn (array $point): array => [$point[1], $point[0]], $geometry->points), array_map(self::swap(...), $geometry->children), $geometry->srid);
    }
}
