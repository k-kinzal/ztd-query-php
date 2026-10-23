<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Spatial;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Value\Spatial\Axes;
use MySqlMemory\Value\Spatial\Geometry;
use MySqlMemory\Value\Spatial\Wkb;
use SqlSemantics\Contract\GrammarRelease;

/**
 * Reads and replaces point coordinates using the retained operand domains.
 * Geographic X/Y follow the reference system's declared axis order; longitude
 * and latitude identify their axes independently of that order. Both operands
 * are evaluated before NULL handling. Replacements preserve the reference ID.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/gis-point-property-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Coordinates
{
    /**
     * @return list<Routine> Coordinate readers and modern setters; semantic resolution owns release availability
     */
    public function routines(): array
    {
        $routines = [];
        foreach (['X', 'Y', 'ST_X', 'ST_Y', 'ST_LONGITUDE', 'ST_LATITUDE'] as $name) {
            $property = str_starts_with($name, 'ST_') ? substr($name, 3) : $name;
            $routines[] = new Routine($name, 1, str_starts_with($name, 'ST_') ? 2 : 1, fn (Frame $f, array $a): float|string|null => $this->value($f, $a, $property));
        }
        return $routines;
    }

    /**
     * @param list<Evaluable> $arguments Point and optional replacement ordinate
     */
    public function value(Frame $frame, array $arguments, string $property): float|string|null
    {
        $raw = FailedGeometry::read($arguments[0], $frame, $frame->context->modes->release === GrammarRelease::MySql5744 ? 'st_' . strtolower($property) : null);
        $coordinate = isset($arguments[1]) ? Convert::toDouble($arguments[1]->evaluate($frame), $arguments[1]->domain(), $frame->context) : null;
        if ($raw === null || (isset($arguments[1]) && $coordinate === null)) {
            return null;
        }
        $bytes = (string) Convert::toText($raw, $arguments[0]->domain());
        $function = 'st_' . strtolower($property);
        $release = $frame->context->modes->release;
        if (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
            $geometry = $this->legacy($bytes, $release, $property);
            return $geometry?->type === 1 ? $geometry->points[0][$property === 'X' ? 0 : 1] : null;
        }
        [$geometry, $definition] = $this->read($frame, $bytes, $function);
        $latitudeFirst = Axes::latitudeFirst($definition);
        $axis = $this->axis($geometry, $property, $latitudeFirst);
        $unit = Axes::angularUnit($definition);
        $meridian = Axes::primeMeridian($definition);
        if ($coordinate === null) {
            return $latitudeFirst === null ? $geometry->points[0][$axis] : ($axis === 0 ? ($geometry->points[0][0] + $meridian) * $unit / $unit - $meridian : $geometry->points[0][1] * $unit / $unit);
        }
        $point = $geometry->points[0];
        $point[$axis] = $coordinate;
        $changed = new Geometry(1, [$point], [], $geometry->srid);
        if ($latitudeFirst !== null) {
            (new Inputs())->coordinates($changed, Axes::angularUnit($definition), $function);
        }
        return Wkb::write($latitudeFirst === null ? $changed : new Geometry(1, [[($point[0] + $meridian) * $unit / $unit - $meridian, $point[1] * $unit / $unit]], [], $geometry->srid));
    }

    /**
     * Legacy coordinate readers reject an unknown type before byte-order diagnostics.
     */
    public function legacy(string $bytes, GrammarRelease $release, string $property): ?Geometry
    {
        if ($release === GrammarRelease::MySql5744 && strlen($bytes) >= 9) {
            $offset = 5;
            $type = Wkb::number($bytes, $offset, $bytes[4] !== "\0");
            if ($type === 0 || $type > 7) {
                throw DataError::GisInvalidData->error('st_' . strtolower($property));
            }
        }
        return (new Parts())->read($bytes, $release, $property);
    }

    /**
     * Resolves the prefix SRID before the geometry and validates input coordinates.
     *
     * @return array{Geometry, string} Point and its current reference definition
     */
    public function read(Frame $frame, string $bytes, string $function): array
    {
        $offset = 0;
        $srid = Wkb::number($bytes, $offset, true);
        if ($srid === null) {
            throw DataError::GisInvalidData->error($function);
        }
        $definition = (new Inputs())->reference($frame, $srid);
        $geometry = Wkb::read($bytes);
        if ($geometry === null || !$geometry->valid()) {
            throw DataError::GisInvalidData->error($function);
        }
        if (Axes::latitudeFirst($definition) !== null) {
            $this->bounds($geometry, Axes::angularUnit($definition), $function);
        }
        if ($geometry->type !== 1) {
            throw DataError::UnexpectedGeometryType->error('POINT', $geometry->name(), $function);
        }
        return [$geometry, $definition];
    }

    /**
     * Selects a stored ordinate, respecting declared geographic X/Y order.
     *
     * @return 0|1 The ordinate's position in stored longitude/latitude order
     */
    public function axis(Geometry $point, string $property, ?bool $latitudeFirst): int
    {
        if (in_array($property, ['LONGITUDE', 'LATITUDE'], true) && $latitudeFirst === null) {
            throw DataError::SrsNotGeographic->error('st_' . strtolower($property), $point->srid);
        }
        return match ($property) {
            'LONGITUDE' => 0,
            'LATITUDE' => 1,
            'X' => $latitudeFirst === true ? 1 : 0,
            default => $latitudeFirst === true ? 0 : 1,
        };
    }

    /**
     * Distinguishes an invalid input geometry from an out-of-range replacement value.
     */
    public function bounds(Geometry $geometry, float $unit, string $function): void
    {
        $longitude = M_PI / $unit;
        $latitude = M_PI_2 / $unit;
        foreach ($geometry->points as [$x, $y]) {
            if ($x <= -$longitude || $x > $longitude) {
                throw DataError::GeometryLongitudeOutOfRange->error($function, sprintf('%.6f', $x), sprintf('%.6f', -$longitude), sprintf('%.6f', $longitude));
            }
            if ($y < -$latitude || $y > $latitude) {
                throw DataError::GeometryLatitudeOutOfRange->error($function, sprintf('%.6f', $y), sprintf('%.6f', -$latitude), sprintf('%.6f', $latitude));
            }
        }
        foreach ($geometry->children as $child) {
            $this->bounds($child, $unit, $function);
        }
    }
}
