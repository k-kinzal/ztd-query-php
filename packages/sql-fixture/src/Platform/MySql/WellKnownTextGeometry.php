<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql;

use Faker\Generator;

/**
 * Writes geometries in the Well-Known Text spelling MySQL parses.
 *
 * A spatial column will not take a made-up string: the server parses WKT and
 * rejects anything else, and each geometry has its own bracketing — a polygon
 * closes back on its first point, a multi-polygon nests one more level than a
 * polygon, and a collection carries whole geometries rather than points. The
 * coordinates themselves are ordinary longitude and latitude, kept away from
 * the poles and the antimeridian so that a shape built by adding an offset
 * stays inside the valid range.
 */
final class WellKnownTextGeometry
{
    /**
     * Writes one point.
     *
     * @param Generator $faker Source of the coordinates
     *
     * @return string A POINT in Well-Known Text
     */
    public function point(Generator $faker): string
    {
        return sprintf('POINT(%f %f)', $faker->longitude(), $faker->latitude());
    }

    /**
     * Writes a line through several points.
     *
     * @param Generator $faker Source of the coordinates
     *
     * @return string A LINESTRING in Well-Known Text
     */
    public function lineString(Generator $faker): string
    {
        $points = [];
        for ($index = 0; $index < $faker->numberBetween(2, 4); $index++) {
            $points[] = sprintf('%f %f', $faker->longitude(), $faker->latitude());
        }

        return 'LINESTRING(' . implode(',', $points) . ')';
    }

    /**
     * Writes a rectangle that closes back on the point it started from.
     *
     * @param Generator $faker Source of the coordinates
     *
     * @return string A POLYGON in Well-Known Text
     */
    public function polygon(Generator $faker): string
    {
        $longitude = $faker->longitude(-170, 170);
        $latitude = $faker->latitude(-80, 80);
        $offset = $faker->randomFloat(2, 0.1, 1.0);

        return 'POLYGON((' . implode(',', [
            sprintf('%f %f', $longitude, $latitude),
            sprintf('%f %f', $longitude + $offset, $latitude),
            sprintf('%f %f', $longitude + $offset, $latitude + $offset),
            sprintf('%f %f', $longitude, $latitude + $offset),
            sprintf('%f %f', $longitude, $latitude),
        ]) . '))';
    }

    /**
     * Writes several points as one geometry.
     *
     * @param Generator $faker Source of the coordinates
     *
     * @return string A MULTIPOINT in Well-Known Text
     */
    public function multiPoint(Generator $faker): string
    {
        $points = [];
        for ($index = 0; $index < $faker->numberBetween(2, 4); $index++) {
            $points[] = sprintf('(%f %f)', $faker->longitude(), $faker->latitude());
        }

        return 'MULTIPOINT(' . implode(',', $points) . ')';
    }

    /**
     * Writes several lines as one geometry.
     *
     * @param Generator $faker Source of the coordinates
     *
     * @return string A MULTILINESTRING in Well-Known Text
     */
    public function multiLineString(Generator $faker): string
    {
        $lines = [];
        for ($line = 0; $line < $faker->numberBetween(2, 3); $line++) {
            $points = [];
            for ($point = 0; $point < $faker->numberBetween(2, 3); $point++) {
                $points[] = sprintf('%f %f', $faker->longitude(), $faker->latitude());
            }
            $lines[] = '(' . implode(',', $points) . ')';
        }

        return 'MULTILINESTRING(' . implode(',', $lines) . ')';
    }

    /**
     * Writes two triangles as one geometry.
     *
     * @param Generator $faker Source of the coordinates
     *
     * @return string A MULTIPOLYGON in Well-Known Text
     */
    public function multiPolygon(Generator $faker): string
    {
        $polygons = [];
        for ($index = 0; $index < 2; $index++) {
            $longitude = $faker->longitude(-170, 170);
            $latitude = $faker->latitude(-80, 80);
            $offset = $faker->randomFloat(2, 0.1, 0.5);
            $polygons[] = '((' . implode(',', [
                sprintf('%f %f', $longitude, $latitude),
                sprintf('%f %f', $longitude + $offset, $latitude),
                sprintf('%f %f', $longitude + $offset, $latitude + $offset),
                sprintf('%f %f', $longitude, $latitude),
            ]) . '))';
        }

        return 'MULTIPOLYGON(' . implode(',', $polygons) . ')';
    }

    /**
     * Writes a point and a line as one geometry of mixed kinds.
     *
     * @param Generator $faker Source of the coordinates
     *
     * @return string A GEOMETRYCOLLECTION in Well-Known Text
     */
    public function collection(Generator $faker): string
    {
        return sprintf('GEOMETRYCOLLECTION(%s,%s)', $this->point($faker), $this->lineString($faker));
    }
}
