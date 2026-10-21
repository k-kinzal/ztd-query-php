<?php

declare(strict_types=1);

namespace MySqlMemory\Value\Spatial;

use MySqlMemory\Value\Real;

/**
 * Writes two-dimensional WKT, retaining component order and signed zero.
 *
 * MySQL 5.6 writes bare MULTIPOINT coordinates. The EMPTY spelling for empty
 * geometry collections starts in 8.0; earlier releases write parentheses.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/gis-format-conversion-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Wkt
{
    /**
     * Writes one complete geometry, or NULL for nonfinite coordinates in modern releases.
     */
    public static function write(Geometry $geometry, bool $legacy = false, bool $emptyKeyword = true): ?string
    {
        if ($geometry->type === 7 && $geometry->children === []) {
            return 'GEOMETRYCOLLECTION' . ($emptyKeyword ? ' EMPTY' : '()');
        }
        $body = self::body($geometry, $legacy, $emptyKeyword);

        return $body === null ? null : ($geometry->type === 7 ? 'GEOMETRYCOLLECTION' : $geometry->name()) . $body;
    }

    /**
     * Writes parentheses and recursively nested coordinates without repeating homogeneous member names.
     */
    public static function body(Geometry $geometry, bool $legacy, bool $emptyKeyword): ?string
    {
        $parts = [];
        foreach ($geometry->points as [$x, $y]) {
            if (!$legacy && (!is_finite($x) || !is_finite($y))) {
                return null;
            }
            $parts[] = (is_finite($x) ? Real::format($x) : '0') . ' ' . (is_finite($y) ? Real::format($y) : '0');
        }
        foreach ($geometry->children as $child) {
            $part = $geometry->type === 7 ? self::write($child, $legacy, $emptyKeyword) : self::body($child, $legacy, $emptyKeyword);
            if ($part === null) {
                return null;
            }
            $parts[] = $legacy && $geometry->type === 4 ? substr($part, 1, -1) : $part;
        }

        return '(' . implode(',', $parts) . ')';
    }
}
