<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Type;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Decimal;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Floating;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\SpatialKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Spatial;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;
use SqlSemantics\Platform\MySql\Statement\Type\TypeName;

/**
 * Lowers the data type productions of every release into type structure.
 *
 * Rule: MYSQL-TYPE-001. Scope: type, int_type, real_type, numeric_type,
 * opt_PRECISION, spatial_type, and through its collaborators the string
 * types, the type parts and cast_type. The structure is the type as
 * requested: its keyword kind, exact length, precision and scale, numeric
 * attributes in written order and character set attribute. Spellings the
 * manual defines as synonyms are one kind (TypeNoise lists them); REAL,
 * `CHAR VARYING` and the LONG forms stay distinct requests. Constructs:
 * Integral, Decimal, Floating, Elementary, Temporal, Spatial, Character,
 * Binary, Enumeration, CastTarget. Terminates: every child is a strict
 * subtree. Source: https://dev.mysql.com/doc/refman/8.4/en/data-types.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TypeRule
{
    /**
     * The integer type keyword productions.
     */
    private const INTEGERS = [
        'int_type: INT_SYM' => IntegralKind::Int, 'int_type: TINYINT' => IntegralKind::TinyInt, 'int_type: SMALLINT' => IntegralKind::SmallInt,
        'int_type: MEDIUMINT' => IntegralKind::MediumInt, 'int_type: BIGINT' => IntegralKind::BigInt, 'int_type: TINYINT_SYM' => IntegralKind::TinyInt,
        'int_type: SMALLINT_SYM' => IntegralKind::SmallInt, 'int_type: MEDIUMINT_SYM' => IntegralKind::MediumInt, 'int_type: BIGINT_SYM' => IntegralKind::BigInt,
    ];

    /**
     * The REAL and DOUBLE keyword productions.
     */
    private const REALS = [
        'real_type: REAL' => FloatingKind::Real, 'real_type: DOUBLE_SYM' => FloatingKind::Double, 'real_type: DOUBLE_SYM PRECISION' => FloatingKind::Double,
        'real_type: REAL_SYM' => FloatingKind::Real, 'real_type: DOUBLE_SYM opt_PRECISION' => FloatingKind::Double,
    ];

    /**
     * The numeric type productions written with a keyword, options and attributes; null is DECIMAL.
     */
    private const NUMERICS = [
        'type: FLOAT_SYM float_options field_options' => FloatingKind::Float, 'type: DECIMAL_SYM float_options field_options' => null,
        'type: NUMERIC_SYM float_options field_options' => null, 'type: FIXED_SYM float_options field_options' => null,
    ];

    /**
     * The keyword productions of the rule numeric_type; null is DECIMAL.
     */
    private const NUMERIC_KEYWORDS = [
        'numeric_type: FLOAT_SYM' => FloatingKind::Float, 'numeric_type: DECIMAL_SYM' => null, 'numeric_type: NUMERIC_SYM' => null, 'numeric_type: FIXED_SYM' => null,
    ];

    /**
     * The productions of a type written as one keyword with an optional length at the given position.
     */
    private const ELEMENTARY = [
        'type: BIT_SYM' => [ElementaryKind::Bit, null], 'type: BIT_SYM field_length' => [ElementaryKind::Bit, 1], 'type: BOOL_SYM' => [ElementaryKind::Boolean, null],
        'type: BOOLEAN_SYM' => [ElementaryKind::Boolean, null], 'type: SERIAL_SYM' => [ElementaryKind::Serial, null], 'type: JSON_SYM' => [ElementaryKind::Json, null],
        'type: VECTOR_SYM opt_field_length' => [ElementaryKind::Vector, 1],
    ];

    /**
     * The temporal type productions, with the position of the precision.
     */
    private const TEMPORALS = [
        'type: DATE_SYM' => [TemporalKind::Date, null], 'type: TIME_SYM type_datetime_precision' => [TemporalKind::Time, 1],
        'type: TIMESTAMP type_datetime_precision' => [TemporalKind::Timestamp, 1], 'type: TIMESTAMP_SYM type_datetime_precision' => [TemporalKind::Timestamp, 1],
        'type: DATETIME type_datetime_precision' => [TemporalKind::DateTime, 1], 'type: DATETIME_SYM type_datetime_precision' => [TemporalKind::DateTime, 1],
    ];

    /**
     * The spatial type keyword productions.
     */
    private const SPATIALS = [
        'spatial_type: GEOMETRY_SYM' => SpatialKind::Geometry, 'spatial_type: GEOMETRYCOLLECTION' => SpatialKind::GeometryCollection,
        'spatial_type: POINT_SYM' => SpatialKind::Point, 'spatial_type: MULTIPOINT' => SpatialKind::MultiPoint, 'spatial_type: LINESTRING' => SpatialKind::LineString,
        'spatial_type: MULTILINESTRING' => SpatialKind::MultiLineString, 'spatial_type: POLYGON' => SpatialKind::Polygon,
        'spatial_type: MULTIPOLYGON' => SpatialKind::MultiPolygon, 'spatial_type: GEOMETRYCOLLECTION_SYM' => SpatialKind::GeometryCollection,
        'spatial_type: MULTIPOINT_SYM' => SpatialKind::MultiPoint, 'spatial_type: LINESTRING_SYM' => SpatialKind::LineString,
        'spatial_type: MULTILINESTRING_SYM' => SpatialKind::MultiLineString, 'spatial_type: POLYGON_SYM' => SpatialKind::Polygon,
        'spatial_type: MULTIPOLYGON_SYM' => SpatialKind::MultiPolygon,
    ];

    /**
     * @var TypePartRule The rules of lengths, precisions and attributes
     */
    public readonly TypePartRule $parts;

    /**
     * @var StringTypeRule The rules of character, binary and enumeration types
     */
    public readonly StringTypeRule $strings;

    /**
     * @var CastTypeRule The rules of cast targets
     */
    public readonly CastTypeRule $casts;

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
        $this->parts = new TypePartRule($lowering);
        $this->strings = new StringTypeRule($lowering, $this->parts);
        $this->casts = new CastTypeRule($lowering, $this->parts);
    }

    /**
     * Lowers a data type.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function type(Node $type): TypeName
    {
        $form = $this->lowering->productions->form($type);

        return $this->numeric($form) ?? $this->keyword($form) ?? $this->strings->type($form) ?? throw ImplementationGap::production($form);
    }

    /**
     * Lowers a cast target.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function castTarget(Node $target): CastTarget
    {
        return $this->casts->target($target);
    }

    /**
     * Lowers the optional fractional seconds precision written after a temporal type keyword.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function precision(Node $precision): ?string
    {
        return $this->parts->length($precision);
    }

    /**
     * Lowers a numeric type production, or answers null for another kind of type.
     *
     * @throws ImplementationGap When a part has no rule
     */
    public function numeric(Form $form): ?TypeName
    {
        if ($form->signature === 'type: int_type opt_field_length field_options') {
            $kind = $this->lowering->productions->form($form->node(0));

            return new Integral(self::INTEGERS[$kind->signature] ?? throw ImplementationGap::production($kind), $this->parts->length($form->node(1)), $this->parts->modifiers($form->node(2)));
        }
        if ($form->signature === 'type: real_type opt_precision field_options') {
            return $this->fraction($this->real($form->node(0)), $form);
        }
        if ($form->signature === 'type: numeric_type float_options field_options') {
            $kind = $this->lowering->productions->form($form->node(0));
            if (!array_key_exists($kind->signature, self::NUMERIC_KEYWORDS)) {
                throw ImplementationGap::production($kind);
            }

            return $this->fraction(self::NUMERIC_KEYWORDS[$kind->signature], $form);
        }

        return array_key_exists($form->signature, self::NUMERICS) ? $this->fraction(self::NUMERICS[$form->signature], $form) : null;
    }

    /**
     * Builds a DECIMAL or approximate type from a production whose second and third symbols are its options and attributes.
     *
     * @throws ImplementationGap When a part has no rule
     */
    public function fraction(?FloatingKind $kind, Form $form): TypeName
    {
        [$precision, $scale] = $this->parts->numbers($form->node(1));
        $modifiers = $this->parts->modifiers($form->node(2));

        return $kind === null ? new Decimal($precision, $scale, $modifiers) : new Floating($kind, $precision, $scale, $modifiers);
    }

    /**
     * Lowers the REAL or DOUBLE keyword of an approximate type.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function real(Node $keyword): FloatingKind
    {
        $form = $this->lowering->productions->form($keyword);
        if ($form->signature === 'real_type: DOUBLE_SYM opt_PRECISION') {
            $precision = $this->lowering->productions->form($form->node(1));
            if ($precision->signature !== 'opt_PRECISION:' && $precision->signature !== 'opt_PRECISION: PRECISION') {
                throw ImplementationGap::production($precision);
            }
        }

        return self::REALS[$form->signature] ?? throw ImplementationGap::production($form);
    }

    /**
     * Tells whether a REAL or DOUBLE keyword is DOUBLE written with PRECISION.
     */
    public function doublePrecision(Node $keyword): bool
    {
        $form = $this->lowering->productions->form($keyword);
        if ($form->signature === 'real_type: DOUBLE_SYM PRECISION') {
            return true;
        }

        return $form->signature === 'real_type: DOUBLE_SYM opt_PRECISION' && $this->lowering->productions->form($form->node(1))->signature === 'opt_PRECISION: PRECISION';
    }

    /**
     * Lowers a type written as one keyword, a temporal type or a spatial type, or answers null for another kind of type.
     *
     * @throws ImplementationGap When a part has no rule
     */
    public function keyword(Form $form): ?TypeName
    {
        if (isset(self::ELEMENTARY[$form->signature])) {
            [$kind, $position] = self::ELEMENTARY[$form->signature];

            return new Elementary($kind, $position === null ? null : $this->parts->length($form->node($position)));
        }
        if (isset(self::TEMPORALS[$form->signature])) {
            [$kind, $position] = self::TEMPORALS[$form->signature];

            return new Temporal($kind, $position === null ? null : $this->parts->length($form->node($position)));
        }
        if ($form->signature === 'type: YEAR_SYM opt_field_length field_options') {
            return new Temporal(TemporalKind::Year, $this->parts->length($form->node(1)), $this->parts->modifiers($form->node(2)));
        }
        if ($form->signature !== 'type: spatial_type') {
            return null;
        }
        $kind = $this->lowering->productions->form($form->node(0));

        return new Spatial(self::SPATIALS[$kind->signature] ?? throw ImplementationGap::production($kind));
    }
}
