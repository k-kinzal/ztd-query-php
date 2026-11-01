<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Aggregate;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Spatial\Properties;
use MySqlMemory\Value\Spatial\Geometry;
use MySqlMemory\Value\Spatial\Wkb;

/**
 * Collects non-NULL spatial aggregate inputs in their evaluation order.
 *
 * Homogeneous points, lines and polygons form their corresponding multi-type;
 * other combinations retain each input as a geometry collection member.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/spatial-aggregate-functions.html.
 *
 * @visibility MySqlMemory
 */
final class SpatialCollection
{
    /**
     * @var list<Geometry> The validated inputs in one spatial reference system
     */
    private array $members = [];

    /**
     * Validates and appends a non-NULL geometry without flattening its members.
     *
     * @throws \MySqlMemory\Error\SqlError When an input is malformed or its reference system is unknown or different
     */
    public function add(Frame $frame, string $bytes): void
    {
        $geometry = (new Properties())->geometry($bytes, 'st_collect');
        if ($geometry->srid !== 0 && $frame->context->variables->instance->registry->spatialCatalog->name($geometry->srid) === null) {
            throw SchemaError::SrsNotFound->error($geometry->srid);
        }
        $first = $this->members[0] ?? null;
        if ($first !== null && $first->srid !== $geometry->srid) {
            throw DataError::GisDifferentSridsAggregation->error('st_collect', $first->srid, $geometry->srid);
        }
        $this->members[] = $geometry;
    }

    /**
     * Returns the narrowest collection type, or NULL when no non-NULL input arrived.
     */
    public function result(): ?string
    {
        $first = $this->members[0] ?? null;
        if ($first === null) {
            return null;
        }
        $types = array_unique(array_map(static fn (Geometry $geometry): int => $geometry->type, $this->members));
        $type = count($types) === 1 && $first->type <= 3 ? $first->type + 3 : 7;

        return Wkb::write(new Geometry($type, [], $this->members, $first->srid));
    }
}
