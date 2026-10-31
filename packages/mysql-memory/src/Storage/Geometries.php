<?php

declare(strict_types=1);

namespace MySqlMemory\Storage;

use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Value\Spatial\Wkb;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\SpatialKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

/**
 * Stores geometry bytes under the subtype and SRID restrictions of a resolved column.
 *
 * Invalid representations and subtype mismatches precede SRID mismatch errors. IGNORE
 * and non-strict modes do not downgrade either error. Verified through SQL on MySQL 8.0.44.
 *
 * @visibility MySqlMemory
 */
final class Geometries
{
    /**
     * Checks a value and preserves the geometry's stored bytes.
     */
    public function value(int|float|string|\MySqlMemory\Evaluation\ExactDecimal|null $value, ColumnDefinition $column, GrammarRelease $release = GrammarRelease::MySql847): ?string
    {
        if ($value === null) {
            if (!$column->nullable() && !in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
                throw DataError::GeometryCannotBeNull->error($column->name);
            }

            return null;
        }
        if (!is_string($value)) {
            throw DataError::InvalidGeometryValue->error();
        }
        $geometry = Wkb::read($value);
        $type = match ($column->spatialKind) {
            SpatialKind::Point => 1,
            SpatialKind::LineString => 2,
            SpatialKind::Polygon => 3,
            SpatialKind::MultiPoint => 4,
            SpatialKind::MultiLineString => 5,
            SpatialKind::MultiPolygon => 6,
            SpatialKind::GeometryCollection => 7,
            SpatialKind::Geometry, null => null,
        };
        if ($geometry === null || $value[4] !== "\1" || ($release !== GrammarRelease::MySql5651 && $type !== null && $geometry->type !== $type)) {
            throw DataError::InvalidGeometryValue->error();
        }
        if ($column->srid !== null && $column->srid !== $geometry->srid) {
            throw DataError::GeometrySridMismatch->error($column->name, $geometry->srid, $column->srid);
        }

        return $value;
    }

    /**
     * Refuses an absent required geometry. An explicit DEFAULT attempts to store invalid bytes;
     * omitted values are refused unconditionally after MySQL 5.7 and only in strict mode in 5.7.
     */
    public function default(ColumnDefinition $column, GrammarRelease $release, bool $explicit, bool $strict = true): void
    {
        if ($column->domain->field !== Field::Geometry || $column->nullable()) {
            return;
        }
        if ($explicit) {
            throw DataError::InvalidGeometryValue->error();
        }
        if ($release !== GrammarRelease::MySql5651 && ($release !== GrammarRelease::MySql5744 || $strict)) {
            throw DataError::NoDefaultForField->error($column->name);
        }
    }
}
