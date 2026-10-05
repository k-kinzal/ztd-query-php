<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Type;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind;

/**
 * Lowers the target type of CAST, CONVERT and JSON_VALUE ... RETURNING.
 *
 * Rule: MYSQL-CAST-TYPE-001. Scope: cast_type. Each production names its
 * target kind and the positions of its length, of its precision and scale,
 * and of its character set attribute. The optional INT after SIGNED and
 * UNSIGNED and PRECISION after DOUBLE are not kept. Constructs: CastTarget.
 * Terminates: every child is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html#function_cast.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CastTypeRule
{
    /**
     * The cast target productions: kind, position of the length or of the precision and scale, position of the character set attribute.
     */
    private const TARGETS = [
        'cast_type: BINARY opt_field_length' => [CastKind::Binary, 1, null], 'cast_type: BINARY_SYM opt_field_length' => [CastKind::Binary, 1, null],
        'cast_type: CHAR_SYM opt_field_length opt_binary' => [CastKind::Char, 1, 2],
        'cast_type: CHAR_SYM opt_field_length opt_charset_with_opt_binary' => [CastKind::Char, 1, 2],
        'cast_type: NCHAR_SYM opt_field_length' => [CastKind::NationalChar, 1, null], 'cast_type: nchar opt_field_length' => [CastKind::NationalChar, 1, null],
        'cast_type: SIGNED_SYM' => [CastKind::Signed, null, null], 'cast_type: SIGNED_SYM INT_SYM' => [CastKind::Signed, null, null],
        'cast_type: UNSIGNED' => [CastKind::Unsigned, null, null], 'cast_type: UNSIGNED INT_SYM' => [CastKind::Unsigned, null, null],
        'cast_type: UNSIGNED_SYM' => [CastKind::Unsigned, null, null], 'cast_type: UNSIGNED_SYM INT_SYM' => [CastKind::Unsigned, null, null],
        'cast_type: DATE_SYM' => [CastKind::Date, null, null], 'cast_type: TIME_SYM type_datetime_precision' => [CastKind::Time, 1, null],
        'cast_type: DATETIME type_datetime_precision' => [CastKind::DateTime, 1, null],
        'cast_type: DATETIME_SYM type_datetime_precision' => [CastKind::DateTime, 1, null],
        'cast_type: DECIMAL_SYM float_options' => [CastKind::Decimal, 1, null], 'cast_type: JSON_SYM' => [CastKind::Json, null, null],
        'cast_type: YEAR_SYM' => [CastKind::Year, null, null], 'cast_type: FLOAT_SYM standard_float_options' => [CastKind::Float, 1, null],
        'cast_type: POINT_SYM' => [CastKind::Point, null, null], 'cast_type: LINESTRING_SYM' => [CastKind::LineString, null, null],
        'cast_type: POLYGON_SYM' => [CastKind::Polygon, null, null], 'cast_type: MULTIPOINT_SYM' => [CastKind::MultiPoint, null, null],
        'cast_type: MULTILINESTRING_SYM' => [CastKind::MultiLineString, null, null], 'cast_type: MULTIPOLYGON_SYM' => [CastKind::MultiPolygon, null, null],
        'cast_type: GEOMETRYCOLLECTION_SYM' => [CastKind::GeometryCollection, null, null],
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     * @param TypePartRule $parts The rules of lengths and attributes
     */
    public function __construct(private readonly Lowering $lowering, private readonly TypePartRule $parts)
    {
    }

    /**
     * Lowers a cast target.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function target(Node $target): CastTarget
    {
        $form = $this->lowering->productions->form($target);
        if ($form->signature === 'cast_type: real_type') {
            $real = $this->lowering->types->real($form->node(0)) === FloatingKind::Real;

            return new CastTarget($real ? CastKind::Real : CastKind::Double, null, null, null, $this->lowering->types->doublePrecision($form->node(0)) ? OptionalWords::Written : OptionalWords::Omitted);
        }
        [$kind, $numbers, $charset] = self::TARGETS[$form->signature] ?? throw ImplementationGap::production($form);
        $words = str_ends_with($form->signature, ' INT_SYM') ? OptionalWords::Written : OptionalWords::Omitted;
        if ($form->signature === 'cast_type: nchar opt_field_length') {
            $this->lowering->types->strings->keyword($form->node(0));
            $words = $this->lowering->productions->form($form->node(0))->signature === 'nchar: NATIONAL_SYM CHAR_SYM' ? OptionalWords::Written : OptionalWords::Omitted;
        }
        [$length, $scale] = $numbers === null ? [null, null] : $this->parts->numbers($form->node($numbers));

        return new CastTarget($kind, $length, $scale, $charset === null ? null : $this->parts->charset($form->node($charset)), $words);
    }
}
