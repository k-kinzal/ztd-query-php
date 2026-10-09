<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Spatial;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Spatial\Wkb;
use Override;

/**
 * A spatial CAST, reading a geometry value before converting its components to the target type.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html#spatial-cast-functions.
 *
 * @visibility MySqlMemory
 */
final class SpatialCast implements Evaluable
{
    /**
     * @param Evaluable $operand The value to convert
     * @param Domain $domain The GEOMETRY result domain
     * @param string $target The spatial type keyword
     */
    public function __construct(public readonly Evaluable $operand, public readonly Domain $domain, public readonly string $target)
    {
    }

    /**
     * Answers the resolved GEOMETRY domain.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Converts a geometry for the row, preserving SQL NULL.
     */
    #[Override]
    public function evaluate(Frame $frame): ?string
    {
        $value = $this->operand->evaluate($frame);
        if ($value === null) {
            return null;
        }
        $geometry = Wkb::read((string) $value);
        if ($geometry === null || !$geometry->valid()) {
            throw DataError::GisInvalidData->error('cast_as_' . strtolower($this->target));
        }
        if ($geometry->srid !== 0 && $frame->context->variables->instance->registry->spatialCatalog->name($geometry->srid) === null) {
            throw \MySqlMemory\Error\Family\SchemaError::SrsNotFound->error($geometry->srid);
        }
        $type = ['POINT' => 1, 'LINESTRING' => 2, 'POLYGON' => 3, 'MULTIPOINT' => 4, 'MULTILINESTRING' => 5, 'MULTIPOLYGON' => 6, 'GEOMETRYCOLLECTION' => 7][$this->target];

        return Wkb::write(Conversions::convert($geometry, $type));
    }
}
