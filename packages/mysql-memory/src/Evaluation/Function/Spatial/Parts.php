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
 * Reads ordered geometry components, ring counts and line closure.
 *
 * The index is converted before geometry validation, including for NULL payloads.
 * Extracted components retain the parent's reference identifier. Modern closure
 * rejects geographic references before testing the geometry's subtype.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/gis-geometrycollection-property-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Parts
{
    /**
     * @return list<Routine> Component readers and their available legacy spellings
     */
    public function routines(): array
    {
        $routines = [new Routine('ST_NUMINTERIORRING', 1, 1, fn (Frame $f, array $a): int|string|null => $this->value($f, $a, 'NUMINTERIORRINGS'))];
        foreach (['GEOMETRYN', 'POINTN', 'STARTPOINT', 'ENDPOINT', 'EXTERIORRING', 'INTERIORRINGN', 'NUMPOINTS', 'NUMINTERIORRINGS', 'ISCLOSED'] as $name) {
            $arity = in_array($name, ['GEOMETRYN', 'POINTN', 'INTERIORRINGN'], true) ? 2 : 1;
            foreach (['', 'ST_'] as $prefix) {
                $routines[] = new Routine($prefix . $name, $arity, $arity, fn (Frame $f, array $a): int|string|null => $this->value($f, $a, $name));
            }
        }
        return $routines;
    }

    /**
     * @param list<Evaluable> $arguments Geometry and optional one-based member index
     */
    public function value(Frame $frame, array $arguments, string $property): int|string|null
    {
        $raw = $arguments[0]->evaluate($frame);
        $index = isset($arguments[1]) ? $this->index($frame, $arguments[1]) : 1;
        if ($raw === null || $index === null) {
            return null;
        }
        $bytes = (string) Convert::toText($raw, $arguments[0]->domain());
        $release = $frame->context->modes->release;
        if ($release === GrammarRelease::MySql5651 && in_array($property, ['NUMPOINTS', 'NUMINTERIORRINGS'], true)) {
            return $this->legacyCount($bytes, $property);
        }
        $geometry = $this->read($bytes, $release, $property);
        if ($geometry === null) {
            return null;
        }
        if (!in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
            $definition = (new Inputs())->reference($frame, $geometry->srid);
            if ($property === 'ISCLOSED' && Axes::latitudeFirst($definition) !== null) {
                throw DataError::GeographicOperationUnsupported->error('st_isclosed', $geometry->name());
            }
        }
        $part = $this->select($geometry, $property, $index);
        if ($release === GrammarRelease::MySql5651 && $part instanceof Geometry && $part->type === 7 && $part->children === []) {
            return null;
        }
        return $part instanceof Geometry ? Wkb::write(new Geometry($part->type, $part->points, $part->children, $geometry->srid)) : $part;
    }

    /**
     * Reads an implicit integer index. User variables and assignments truncate
     * text silently here; explicit casts keep their separate warning policy.
     */
    public function index(Frame $frame, Evaluable $argument): ?int
    {
        $value = $argument->evaluate($frame);
        $domain = $argument->domain();
        while ($argument instanceof \MySqlMemory\Evaluation\Leaf\Retyped) {
            $argument = $argument->evaluable;
        }
        if ($argument instanceof \MySqlMemory\Evaluation\Leaf\Assignment || $argument instanceof \MySqlMemory\Evaluation\Leaf\UserVariableRead) {
            $domain = $domain->withQuiet();
        }
        return Convert::toInteger($value, $domain, $frame->context);
    }

    /**
     * Reads the payload with release-specific completeness and byte-order checks.
     */
    public function read(string $bytes, GrammarRelease $release, string $property): ?Geometry
    {
        if ($release !== GrammarRelease::MySql5651) {
            return (new Properties())->geometry($bytes, 'st_' . strtolower($property));
        }
        $srid = (new Srid())->legacy($bytes);
        if ($srid === null) {
            return null;
        }
        $offset = 4;
        $geometry = Wkb::geometry(substr_replace($bytes, "\1", 4, 1), $offset, 0, true);
        return $geometry === null || !(new Properties())->readable($geometry) ? null : new Geometry($geometry->type, $geometry->points, $geometry->children, $srid);
    }

    /**
     * Reads MySQL 5.6's declared counts without requiring a complete coordinate payload.
     */
    public function legacyCount(string $bytes, string $property): ?int
    {
        $offset = 5;
        $type = strlen($bytes) < 9 ? null : Wkb::number($bytes, $offset, true);
        if ($type !== ($property === 'NUMPOINTS' ? 2 : 3)) {
            return null;
        }
        $offset = 9;
        $count = Wkb::number($bytes, $offset, true);
        return $count === null || ($property === 'NUMINTERIORRINGS' && $count === 0) ? null : $count - ($property === 'NUMINTERIORRINGS' ? 1 : 0);
    }

    /**
     * Selects only immediate members, without flattening nested collections.
     */
    public function select(Geometry $geometry, string $property, int $index): Geometry|int|null
    {
        return match ($property) {
            'GEOMETRYN' => $geometry->type >= 4 && $index > 0 ? ($geometry->children[$index - 1] ?? null) : null,
            'POINTN' => $this->point($geometry, $index),
            'STARTPOINT' => $this->point($geometry, 1),
            'ENDPOINT' => $this->point($geometry, count($geometry->points)),
            'EXTERIORRING' => $geometry->type === 3 ? ($geometry->children[0] ?? null) : null,
            'INTERIORRINGN' => $geometry->type === 3 && $index > 0 ? ($geometry->children[$index] ?? null) : null,
            'NUMPOINTS' => $geometry->type === 2 ? count($geometry->points) : null,
            'NUMINTERIORRINGS' => $geometry->type === 3 ? count($geometry->children) - 1 : null,
            default => $this->closed($geometry),
        };
    }

    /**
     * Reads a one-based coordinate only from a linestring.
     */
    public function point(Geometry $geometry, int $index): ?Geometry
    {
        return $geometry->type === 2 && $index > 0 && isset($geometry->points[$index - 1]) ? new Geometry(1, [$geometry->points[$index - 1]]) : null;
    }

    /**
     * Tests both endpoints of each immediate line in a multilinestring.
     */
    public function closed(Geometry $geometry): ?int
    {
        if ($geometry->type === 2) {
            return $geometry->points !== [] && $geometry->points[0] === $geometry->points[count($geometry->points) - 1] ? 1 : 0;
        }
        if ($geometry->type !== 5) {
            return null;
        }
        foreach ($geometry->children as $line) {
            if ($this->closed($line) !== 1) {
                return 0;
            }
        }
        return 1;
    }
}
