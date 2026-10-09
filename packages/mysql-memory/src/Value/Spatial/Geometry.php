<?php

declare(strict_types=1);

namespace MySqlMemory\Value\Spatial;

/**
 * A two-dimensional geometry: coordinates for a point or line, rings for a polygon, and members for a collection.
 *
 * Coordinates retain their order; the spatial reference identifier belongs to the complete value.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/gis-data-formats.html.
 *
 * @visibility MySqlMemory
 */
final class Geometry
{
    /**
     * @param int $type The WKB type, 1 through 7
     * @param list<array{float, float}> $points Coordinates of a point or line
     * @param list<self> $children Polygon rings or collection members
     * @param int $srid The spatial reference identifier
     */
    public function __construct(public readonly int $type, public readonly array $points = [], public readonly array $children = [], public readonly int $srid = 0)
    {
    }

    /**
     * Answers the type name used in spatial cast diagnostics.
     */
    public function name(): string
    {
        return [1 => 'POINT', 2 => 'LINESTRING', 3 => 'POLYGON', 4 => 'MULTIPOINT', 5 => 'MULTILINESTRING', 6 => 'MULTIPOLYGON', 7 => 'GEOMCOLLECTION'][$this->type];
    }

    /**
     * Tells whether every component has a valid two-dimensional representation.
     *
     * A polygon ring contains at least four points, with the last equal to the first. A geometry
     * collection may be empty; other collections may not. MySQL 5.6 also accepts a one-point line.
     */
    public function valid(bool $legacy = false): bool
    {
        foreach ($this->points as [$x, $y]) {
            if (!is_finite($x) || !is_finite($y)) {
                return false;
            }
        }
        if ($this->type <= 2) {
            return $this->type === 1 ? count($this->points) === 1 : count($this->points) >= ($legacy ? 1 : 2);
        }
        if ($this->type !== 7 && $this->children === []) {
            return false;
        }
        foreach ($this->children as $child) {
            $expected = [3 => 2, 4 => 1, 5 => 2, 6 => 3][$this->type] ?? $child->type;
            if ($child->type !== $expected || !$child->valid($legacy) || ($this->type === 3 && !$child->ring())) {
                return false;
            }
        }

        return true;
    }

    /**
     * Tells whether a line has the coordinate count and closure of a polygon ring.
     */
    public function ring(): bool
    {
        return $this->type === 2 && count($this->points) >= 4 && $this->points[0] === $this->points[count($this->points) - 1];
    }
}
