<?php

declare(strict_types=1);

namespace MySqlMemory\Value\Spatial;

/**
 * The coordinate envelope of a geometry, independent of its component shapes.
 *
 * Interior intersection distinguishes containment from boundary-only coverage.
 * A zero-width dimension has the point itself as its interior.
 * Source: https://docs.oracle.com/cd/E17952_01/mysql-5.7-en/spatial-relation-functions-mbr.html.
 *
 * @visibility MySqlMemory
 */
final class Bounds
{
    /**
     * @return array{float, float, float, float}|null The x/y minima and maxima, or no envelope for an empty geometry
     */
    public function of(Geometry $geometry): ?array
    {
        $bounds = null;
        foreach ($geometry->points as [$x, $y]) {
            $bounds = $this->merge($bounds, [$x, $y, $x, $y]);
        }
        foreach ($geometry->children as $child) {
            $next = $this->of($child);
            if ($next !== null) {
                $bounds = $this->merge($bounds, $next);
            }
        }
        return $bounds;
    }

    /**
     * @param array{float, float, float, float}|null $left The accumulated envelope
     * @param array{float, float, float, float} $right The next envelope
     * @return array{float, float, float, float} The enclosing envelope
     */
    public function merge(?array $left, array $right): array
    {
        return $left === null ? $right : [min($left[0], $right[0]), min($left[1], $right[1]), max($left[2], $right[2]), max($left[3], $right[3])];
    }

    /**
     * @param array{float, float, float, float} $outer The containing envelope
     * @param array{float, float, float, float} $inner The contained envelope
     * @param bool $boundary Whether boundary-only coverage suffices, as in MySQL 5.6
     */
    public function contains(array $outer, array $inner, bool $boundary): bool
    {
        if ($inner[0] < $outer[0] || $inner[1] < $outer[1] || $inner[2] > $outer[2] || $inner[3] > $outer[3]) {
            return false;
        }
        return $boundary || (($outer[0] === $outer[2] || ($inner[2] > $outer[0] && $inner[0] < $outer[2])) && ($outer[1] === $outer[3] || ($inner[3] > $outer[1] && $inner[1] < $outer[3])));
    }
}
