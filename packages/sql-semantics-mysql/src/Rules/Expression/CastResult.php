<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression;

use SqlSemantics\Platform\MySql\Statement\Type\Binary;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Decimal;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Floating;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\SpatialKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Spatial;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * Derives the type and NULL fact of a cast to a target type.
 *
 * Rule: MYSQL-CAST-RESULT-001. BINARY[(n)] is VARBINARY[(n)]; CHAR[(n)] is
 * VARCHAR[(n)] with its character set attribute, NCHAR the national
 * VARCHAR; SIGNED and UNSIGNED are BIGINT; DATE, TIME[(p)], DATETIME[(p)],
 * YEAR and DECIMAL[(m[,d])] are those types; JSON is JSON; DOUBLE is DOUBLE;
 * FLOAT is FLOAT without a precision or with one of at most 24 and DOUBLE
 * otherwise; REAL is FLOAT under the session mode REAL_AS_FLOAT and DOUBLE
 * otherwise: the profile does not hold the mode, so the type is the known
 * choice of FLOAT and DOUBLE; the spatial targets are their
 * geometry types. A cast to a temporal type or YEAR yields NULL for a value
 * that is not a valid date or time, so it can always be NULL; every other
 * cast is NULL only when its operand is. Terminates: no recursion.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html#function_cast.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CastResult
{
    /**
     * The geometry types of the spatial targets.
     */
    private const SPATIAL = [
        'POINT' => SpatialKind::Point, 'LINESTRING' => SpatialKind::LineString, 'POLYGON' => SpatialKind::Polygon,
        'MULTIPOINT' => SpatialKind::MultiPoint, 'MULTILINESTRING' => SpatialKind::MultiLineString, 'MULTIPOLYGON' => SpatialKind::MultiPolygon,
        'GEOMETRYCOLLECTION' => SpatialKind::GeometryCollection,
    ];

    /**
     * Answers the type of a cast to a target.
     */
    public function type(CastTarget $target): TypeFact
    {
        return match ($target->kind) {
            CastKind::Binary => new Known(new Binary(BinaryKind::VarBinary, $target->length)),
            CastKind::Char => new Known(new Character(CharacterKind::VarChar, $target->length, false, $target->charset)),
            CastKind::NationalChar => new Known(new Character(CharacterKind::VarChar, $target->length, true)),
            CastKind::Signed => new Known(new Integral(IntegralKind::BigInt)),
            CastKind::Unsigned => new Known(new Integral(IntegralKind::BigInt, null, [NumericModifier::Unsigned])),
            CastKind::Date => new Known(new Temporal(TemporalKind::Date)),
            CastKind::Time => new Known(new Temporal(TemporalKind::Time, $target->length)),
            CastKind::DateTime => new Known(new Temporal(TemporalKind::DateTime, $target->length)),
            CastKind::Year => new Known(new Temporal(TemporalKind::Year)),
            CastKind::Decimal => new Known(new Decimal($target->length, $target->scale)),
            CastKind::Json => new Known(new Elementary(ElementaryKind::Json)),
            CastKind::Real => new Choice([new Floating(FloatingKind::Float), new Floating(FloatingKind::Double)]),
            CastKind::Double => new Known(new Floating(FloatingKind::Double)),
            CastKind::Float => new Known(new Floating($target->length === null || (int) $target->length <= 24 ? FloatingKind::Float : FloatingKind::Double)),
            CastKind::Point, CastKind::LineString, CastKind::Polygon, CastKind::MultiPoint, CastKind::MultiLineString,
            CastKind::MultiPolygon, CastKind::GeometryCollection => new Known(new Spatial(self::SPATIAL[$target->kind->value])),
        };
    }

    /**
     * Answers the NULL fact of a cast to a target of an operand with a NULL fact.
     */
    public function nullability(CastTarget $target, Nullability $operand): Nullability
    {
        $temporal = in_array($target->kind, [CastKind::Date, CastKind::Time, CastKind::DateTime, CastKind::Year], true);

        return $temporal ? Nullability::Nullable : $operand;
    }
}
