<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Value;

use Faker\Generator;

/**
 * Constructs valid Well Known Text geometry literals.
 *
 * @visibility root
 */
final class SpatialGenerator
{
    /**
     * Generates point.
     */
    public function generatePoint(Generator $faker): string
    {
        $lon = $faker->longitude();
        $lat = $faker->latitude();
        return sprintf('POINT(%f %f)', $lon, $lat);
    }

    /**
     * Generates line string.
     */
    public function generateLineString(Generator $faker): string
    {
        $pointCount = $faker->numberBetween(2, 4);
        $points = [];
        for ($i = 0; $i < $pointCount; $i++) {
            $points[] = sprintf('%f %f', $faker->longitude(), $faker->latitude());
        }
        return 'LINESTRING(' . implode(',', $points) . ')';
    }

    /**
     * Generates polygon.
     */
    public function generatePolygon(Generator $faker): string
    {
        $baseLon = $faker->longitude(-170, 170);
        $baseLat = $faker->latitude(-80, 80);
        $offset = $faker->randomFloat(2, 0.1, 1.0);

        $points = [
            sprintf('%f %f', $baseLon, $baseLat),
            sprintf('%f %f', $baseLon + $offset, $baseLat),
            sprintf('%f %f', $baseLon + $offset, $baseLat + $offset),
            sprintf('%f %f', $baseLon, $baseLat + $offset),
            sprintf('%f %f', $baseLon, $baseLat),
        ];

        return 'POLYGON((' . implode(',', $points) . '))';
    }

    /**
     * Generates multi point.
     */
    public function generateMultiPoint(Generator $faker): string
    {
        $pointCount = $faker->numberBetween(2, 4);
        $points = [];
        for ($i = 0; $i < $pointCount; $i++) {
            $points[] = sprintf('(%f %f)', $faker->longitude(), $faker->latitude());
        }
        return 'MULTIPOINT(' . implode(',', $points) . ')';
    }

    /**
     * Generates multi line string.
     */
    public function generateMultiLineString(Generator $faker): string
    {
        $lineCount = $faker->numberBetween(2, 3);
        $lines = [];
        for ($i = 0; $i < $lineCount; $i++) {
            $pointCount = $faker->numberBetween(2, 3);
            $points = [];
            for ($j = 0; $j < $pointCount; $j++) {
                $points[] = sprintf('%f %f', $faker->longitude(), $faker->latitude());
            }
            $lines[] = '(' . implode(',', $points) . ')';
        }
        return 'MULTILINESTRING(' . implode(',', $lines) . ')';
    }

    /**
     * Generates multi polygon.
     */
    public function generateMultiPolygon(Generator $faker): string
    {
        $polygons = [];
        for ($i = 0; $i < 2; $i++) {
            $baseLon = $faker->longitude(-170, 170);
            $baseLat = $faker->latitude(-80, 80);
            $offset = $faker->randomFloat(2, 0.1, 0.5);

            $points = [
                sprintf('%f %f', $baseLon, $baseLat),
                sprintf('%f %f', $baseLon + $offset, $baseLat),
                sprintf('%f %f', $baseLon + $offset, $baseLat + $offset),
                sprintf('%f %f', $baseLon, $baseLat),
            ];
            $polygons[] = '((' . implode(',', $points) . '))';
        }
        return 'MULTIPOLYGON(' . implode(',', $polygons) . ')';
    }

    /**
     * Generates geometry collection.
     */
    public function generateGeometryCollection(Generator $faker): string
    {
        $point = $this->generatePoint($faker);
        $lineString = $this->generateLineString($faker);
        return "GEOMETRYCOLLECTION({$point},{$lineString})";
    }
}
