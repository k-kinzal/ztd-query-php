<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Spatial;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Value\Spatial\Geometry;

/**
 * The spatial CAST conversions, preserving coordinates, component order and the source SRID.
 *
 * A collection converts to a simple geometry only when it contains one appropriate member;
 * conversion to a polygon also checks closure and exterior/interior ring direction.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html#spatial-cast-functions.
 *
 * @visibility MySqlMemory
 */
final class Conversions
{
    /**
     * Converts a well-formed geometry to a spatial target.
     *
     * @throws \MySqlMemory\Error\SqlError When the types or number of members cannot convert, or a ring is reversed
     */
    public static function convert(Geometry $from, int $type): Geometry
    {
        if ($from->type === $type) {
            return $from;
        }
        $result = match ($type) {
            1 => in_array($from->type, [4, 7], true) ? self::single($from, 1) : null,
            2 => self::line($from),
            3 => self::polygon($from),
            4, 6 => self::multiple($from, $type),
            5 => self::lines($from),
            7 => new Geometry(7, [], $from->type <= 3 ? [$from] : $from->children),
            default => null,
        };
        if ($result === null || !$result->valid()) {
            throw DataError::InvalidSpatialCast->error($from->name(), (new Geometry($type))->name());
        }
        if (($type === 3 && in_array($from->type, [2, 5], true)) || ($type === 6 && $from->type === 5)) {
            foreach ($type === 3 ? [$result] : $result->children as $polygon) {
                self::direction($polygon, $from->name(), $result->name());
            }
        }

        return new Geometry($result->type, $result->points, $result->children, $from->srid);
    }

    /**
     * Promotes points or polygons into multi-geometries, expanding line coordinates or rings when appropriate.
     */
    public static function multiple(Geometry $from, int $type): ?Geometry
    {
        if ($from->type === $type - 3) {
            return new Geometry($type, [], [$from]);
        }
        if ($type === 4 && $from->type === 2) {
            return new Geometry(4, [], array_map(static fn (array $point): Geometry => new Geometry(1, [$point]), $from->points));
        }
        if ($type === 6 && $from->type === 5) {
            return new Geometry(6, [], array_map(static fn (Geometry $ring): Geometry => new Geometry(3, [], [$ring]), $from->children));
        }

        return self::collection($from, $type);
    }

    /**
     * Extracts the sole member when its type agrees with the requested type.
     */
    public static function single(Geometry $from, int $type): ?Geometry
    {
        return count($from->children) === 1 && $from->children[0]->type === $type ? $from->children[0] : null;
    }

    /**
     * Converts a single-ring polygon, a multipoint or a single-line collection into a line.
     */
    public static function line(Geometry $from): ?Geometry
    {
        return match ($from->type) {
            3, 5, 7 => self::single($from, 2),
            4 => new Geometry(2, array_map(static fn (Geometry $point): array => $point->points[0], $from->children)),
            default => null,
        };
    }

    /**
     * Converts a line or multiline into rings, or extracts the sole polygon of a collection.
     */
    public static function polygon(Geometry $from): ?Geometry
    {
        return match ($from->type) {
            2 => new Geometry(3, [], [$from]),
            5 => new Geometry(3, [], $from->children),
            6, 7 => self::single($from, 3),
            default => null,
        };
    }

    /**
     * Converts a line, polygon rings or polygons without interior rings into a multiline.
     */
    public static function lines(Geometry $from): ?Geometry
    {
        if ($from->type === 6) {
            $rings = [];
            foreach ($from->children as $polygon) {
                $ring = self::single($polygon, 2);
                if ($ring === null) {
                    return null;
                }
                $rings[] = $ring;
            }

            return new Geometry(5, [], $rings);
        }

        return match ($from->type) {
            2 => new Geometry(5, [], [$from]),
            3 => new Geometry(5, [], $from->children),
            default => self::collection($from, 5),
        };
    }

    /**
     * Converts a nonempty geometry collection of homogeneous members into a multi-geometry.
     */
    public static function collection(Geometry $from, int $type): ?Geometry
    {
        if ($from->type !== 7 || $from->children === []) {
            return null;
        }
        foreach ($from->children as $child) {
            if ($child->type !== $type - 3) {
                return null;
            }
        }

        return new Geometry($type, [], $from->children);
    }

    /**
     * Requires a counterclockwise exterior ring and clockwise interior rings.
     */
    public static function direction(Geometry $polygon, string $from, string $to): void
    {
        foreach ($polygon->children as $index => $ring) {
            $area = 0.0;
            for ($i = 1; $i < count($ring->points); ++$i) {
                [$x, $y] = $ring->points[$i - 1];
                [$nextX, $nextY] = $ring->points[$i];
                $area += $x * $nextY - $nextX * $y;
            }
            if (($index === 0 && $area < 0) || ($index > 0 && $area > 0)) {
                throw DataError::PolygonRingDirection->error($from, $to);
            }
        }
    }
}
