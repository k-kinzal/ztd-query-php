<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Spatial;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Value\Spatial\Geometry;
use MySqlMemory\Value\Spatial\Wkb;
use SqlSemantics\Contract\GrammarRelease;

/**
 * Reads structural properties of a geometry without computing topological relationships.
 *
 * Empty collections have no dimension. Geometry type and emptiness in MySQL 5.6 read only
 * a recognized header, whereas newer releases require complete little-endian representations.
 * Coordinate finiteness and polygon closure do not affect these structural properties.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/gis-general-property-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Properties
{
    /**
     * Answers the current and legacy names, leaving release availability to SQL Semantics.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        $routines = [];
        foreach (['DIMENSION', 'GEOMETRYTYPE', 'ISEMPTY', 'NUMGEOMETRIES'] as $property) {
            foreach ([$property, 'ST_' . $property] as $name) {
                $routines[] = new Routine($name, 1, 1, fn (Frame $frame, array $arguments): int|string|null => $this->value($frame, $arguments[0], $property));
            }
        }

        return $routines;
    }

    /**
     * Evaluates the geometry and reads its requested property.
     *
     * @throws \MySqlMemory\Error\SqlError When its encoding or spatial reference cannot be read
     */
    public function value(Frame $frame, Evaluable $argument, string $property): int|string|null
    {
        $raw = $argument->evaluate($frame);
        if ($raw === null) {
            return null;
        }
        $bytes = (string) Convert::toText($raw, $argument->domain());
        $release = $frame->context->modes->release;
        if ($release === GrammarRelease::MySql5651) {
            return $this->legacy($bytes, $property);
        }
        $geometry = $this->geometry($bytes, 'st_' . strtolower($property));
        if ($release !== GrammarRelease::MySql5744 && $geometry->srid !== 0 && $frame->context->variables->instance->registry->spatialCatalog->name($geometry->srid) === null) {
            throw SchemaError::SrsNotFound->error($geometry->srid);
        }

        return match ($property) {
            'GEOMETRYTYPE' => $release === GrammarRelease::MySql5744 && $geometry->type === 7 ? 'GEOMETRYCOLLECTION' : $geometry->name(),
            'ISEMPTY' => $this->dimension($geometry) === null ? 1 : 0,
            'NUMGEOMETRIES' => $geometry->type >= 4 ? count($geometry->children) : null,
            default => $this->dimension($geometry),
        };
    }

    /**
     * Reads a complete structural geometry and preserves the server's ordered byte-order diagnostics.
     *
     * @throws \MySqlMemory\Error\SqlError When its byte order or structure is refused
     */
    public function geometry(string $bytes, string $function): Geometry
    {
        if (strlen($bytes) >= 9 && $bytes[4] !== "\1") {
            $error = DataError::GeometryNotLittleEndian;
            throw new \MySqlMemory\Error\SqlError($error, $error->message(), following: [[DataError::GisInvalidData->number(), DataError::GisInvalidData->message($function)]]);
        }
        $geometry = Wkb::read($bytes, true);
        if ($geometry === null || !$this->readable($geometry)) {
            throw DataError::GisInvalidData->error($function);
        }

        return $geometry;
    }

    /**
     * Checks counts and member types without imposing coordinate or topological constraints.
     */
    public function readable(Geometry $geometry): bool
    {
        if ($geometry->type <= 2) {
            return $geometry->points !== [];
        }
        if ($geometry->type !== 7 && $geometry->children === []) {
            return false;
        }
        foreach ($geometry->children as $child) {
            $expected = [3 => 2, 4 => 1, 5 => 2, 6 => 3][$geometry->type] ?? $child->type;
            if ($child->type !== $expected || !$this->readable($child)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Answers the largest member dimension, or NULL when a collection has no coordinates.
     */
    public function dimension(Geometry $geometry): ?int
    {
        if ($geometry->type <= 3) {
            return $geometry->type - 1;
        }
        $dimension = null;
        foreach ($geometry->children as $child) {
            $next = $this->dimension($child);
            if ($next !== null) {
                $dimension = $dimension === null ? $next : max($dimension, $next);
            }
        }

        return $dimension;
    }

    /**
     * Reads legacy header properties; dimension additionally needs the relevant coordinate payload.
     */
    public function legacy(string $bytes, string $property): int|string|null
    {
        $offset = 5;
        $type = strlen($bytes) < 9 ? null : Wkb::number($bytes, $offset, true);
        if ($type === null || $type < 1 || $type > 7) {
            return null;
        }
        if ($property === 'GEOMETRYTYPE') {
            return $type === 7 ? 'GEOMETRYCOLLECTION' : (new Geometry($type))->name();
        }
        if ($property === 'ISEMPTY') {
            return 0;
        }
        if ($property === 'NUMGEOMETRIES') {
            $offset = 9;
            $count = $type >= 4 ? Wkb::number($bytes, $offset, true) : null;

            return $count === 0 ? null : $count;
        }
        if ($type === 1) {
            return 0;
        }
        $offset = 4;
        $geometry = Wkb::geometry(substr_replace($bytes, "\1", 4, 1), $offset, 0, true);

        return $geometry === null || !$this->readable($geometry) ? null : $this->dimension($geometry);
    }
}
