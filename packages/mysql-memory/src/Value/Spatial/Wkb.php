<?php

declare(strict_types=1);

namespace MySqlMemory\Value\Spatial;

/**
 * Reads and writes a MySQL geometry value: four little-endian SRID bytes followed by two-dimensional WKB.
 *
 * WKB accepts either byte order independently for each nested geometry. Bounds are checked
 * before reading coordinates or allocating members, including counts and nesting depth.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/gis-data-formats.html.
 *
 * @visibility MySqlMemory
 */
final class Wkb
{
    /**
     * Reads a complete value, answering null for a malformed or trailing representation.
     */
    public static function read(string $bytes): ?Geometry
    {
        if (strlen($bytes) < 9) {
            return null;
        }
        $offset = 0;
        $srid = self::number($bytes, $offset, true) ?? 0;
        $offset = 4;
        $geometry = self::geometry($bytes, $offset, 0);

        return $geometry === null || $offset !== strlen($bytes) ? null : new Geometry($geometry->type, $geometry->points, $geometry->children, $srid);
    }

    /**
     * Writes a value in little-endian form, preserving its coordinates, member order and SRID.
     */
    public static function write(Geometry $geometry): string
    {
        return pack('V', $geometry->srid) . self::body($geometry);
    }

    /**
     * Writes the WKB payload without a spatial reference prefix.
     */
    public static function body(Geometry $geometry): string
    {
        $head = "\1" . pack('V', $geometry->type);
        if ($geometry->type <= 2) {
            return $head . ($geometry->type === 2 ? pack('V', count($geometry->points)) : '') . self::coordinates($geometry->points);
        }
        $body = '';
        foreach ($geometry->children as $child) {
            $body .= $geometry->type === 3 ? pack('V', count($child->points)) . self::coordinates($child->points) : self::body($child);
        }

        return $head . pack('V', count($geometry->children)) . $body;
    }

    /**
     * Writes coordinate pairs as little-endian doubles.
     *
     * @param list<array{float, float}> $points
     */
    public static function coordinates(array $points): string
    {
        $bytes = '';
        foreach ($points as [$x, $y]) {
            $bytes .= pack('ee', $x, $y);
        }

        return $bytes;
    }

    /**
     * Reads one WKB geometry at a byte offset and advances past its payload.
     */
    public static function geometry(string $bytes, int &$offset, int $depth): ?Geometry
    {
        if ($depth >= 64 || strlen($bytes) - $offset < 5 || ord($bytes[$offset]) > 1) {
            return null;
        }
        $little = $bytes[$offset++] === "\1";
        $type = self::number($bytes, $offset, $little);
        if ($type === null || $type < 1 || $type > 7) {
            return null;
        }
        if ($type <= 2) {
            $points = self::points($bytes, $offset, $little, $type === 1 ? 1 : null);

            return $points === null ? null : new Geometry($type, $points);
        }
        $count = self::number($bytes, $offset, $little);
        if ($count === null || $count > intdiv(strlen($bytes) - $offset, 4)) {
            return null;
        }
        $children = [];
        for ($i = 0; $i < $count; ++$i) {
            $points = $type === 3 ? self::points($bytes, $offset, $little) : null;
            $child = $type === 3 ? ($points === null ? null : new Geometry(2, $points)) : self::geometry($bytes, $offset, $depth + 1);
            if ($child === null) {
                return null;
            }
            $children[] = $child;
        }

        return new Geometry($type, [], $children);
    }

    /**
     * Reads an unsigned four-byte count, advancing only when four bytes remain.
     */
    public static function number(string $bytes, int &$offset, bool $little): ?int
    {
        if (strlen($bytes) - $offset < 4) {
            return null;
        }
        $read = unpack($little ? 'V' : 'N', $bytes, $offset)[1] ?? null;
        $number = is_int($read) ? $read : null;
        $offset += 4;

        return $number;
    }

    /**
     * Reads coordinate pairs, with an explicit count or a count at the current offset.
     *
     * @return list<array{float, float}>|null
     */
    public static function points(string $bytes, int &$offset, bool $little, ?int $count = null): ?array
    {
        $count ??= self::number($bytes, $offset, $little);
        if ($count === null || $count > intdiv(strlen($bytes) - $offset, 16)) {
            return null;
        }
        $points = [];
        for ($i = 0; $i < $count; ++$i) {
            $pair = unpack($little ? 'e2' : 'E2', $bytes, $offset);
            $x = $pair[1] ?? null;
            $y = $pair[2] ?? null;
            if (!is_float($x) || !is_float($y)) {
                return null;
            }
            $points[] = [$x, $y];
            $offset += 16;
        }

        return $points;
    }
}
