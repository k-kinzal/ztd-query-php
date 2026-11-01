<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A spatial target cannot be the result type of JSON_VALUE.
 *
 * MySQL 8.0.44, 8.4.7 and 9.1.0 report this before binding the document,
 * including inside a stored program's unselected branch.
 *
 * @visibility public
 * @example Describing an impossible JSON conversion
 *     (new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\InvalidJsonCast(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Point))->message() // => 'Invalid cast from JSON to POINT.'
 */
final class InvalidJsonCast implements Diagnostic
{
    use Snapshot;

    /**
     * @param CastKind $target The spatial result type
     */
    public function __construct(public readonly CastKind $target)
    {
        Check::input(in_array($target, [CastKind::Point, CastKind::LineString, CastKind::Polygon, CastKind::MultiPoint, CastKind::MultiLineString, CastKind::MultiPolygon, CastKind::GeometryCollection], true), 'An invalid JSON spatial conversion names a spatial target.');
    }

    /**
     * Describes the intrinsic target conflict.
     */
    public function message(): string
    {
        return 'Invalid cast from JSON to ' . $this->target->value . '.';
    }
}
