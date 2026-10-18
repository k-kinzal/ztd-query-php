<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression\Conversion;

use SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\TooBigPrecision;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;

/**
 * Diagnoses cast target restrictions independently of the operand and execution.
 *
 * Full semantic derivation and completed-prefix lowering share these rules.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CastProblems
{
    /**
     * Excludes spatial results and invalid fractional precision from JSON_VALUE.
     * These are completed-call conditions even in unselected routine branches.
     */
    public function returning(?CastTarget $target): \SqlSemantics\Platform\MySql\Statement\Expression\Problem\InvalidJsonCast|TooBigPrecision|\SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize|null
    {
        if ($target === null) {
            return null;
        }
        if ($target->kind === CastKind::Decimal) {
            if (($target->length === null ? 10 : (int) $target->length) < (int) $target->scale) {
                return new \SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize(\SqlSemantics\Platform\MySql\Statement\Type\Problem\TypeLimit::ScaleExceedsPrecision, '');
            }
            return (new \SqlSemantics\Platform\MySql\Rules\Typing\TypeLimits())->fractional(new \SqlSemantics\Platform\MySql\Statement\Type\Decimal($target->length, $target->scale), '');
        }
        if (in_array($target->kind, [CastKind::Char, CastKind::NationalChar, CastKind::Binary], true) && (int) $target->length > 4294967295) {
            return new \SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize(\SqlSemantics\Platform\MySql\Statement\Type\Problem\TypeLimit::Width, 'cast as char', (int) $target->length, 4294967295);
        }
        return in_array($target->kind, [CastKind::Point, CastKind::LineString, CastKind::Polygon, CastKind::MultiPoint, CastKind::MultiLineString, CastKind::MultiPolygon, CastKind::GeometryCollection], true)
            ? new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\InvalidJsonCast($target->kind) : $this->precision($target);
    }

    /**
     * Checks an array's element kind before its precision, as the completed cast does.
     */
    public function target(CastTarget $target, bool $array): NotSupportedYet|TooBigPrecision|null
    {
        $refusal = $array ? $this->array($target) : null;

        return $refusal === null ? $this->precision($target) : new NotSupportedYet($refusal);
    }

    /**
     * Answers a precision beyond the cast target's limit, before resolving its operand.
     *
     * FLOAT accepts at most 53 bits and reports an overflowing request as a signed 32-bit
     * integer. Temporal fractions accept at most 6 digits. Verified on MySQL 8.4.7.
     */
    public function precision(CastTarget $target): ?TooBigPrecision
    {
        $maximum = match ($target->kind) {
            CastKind::Float => 53,
            CastKind::Time, CastKind::DateTime => 6,
            CastKind::Signed, CastKind::Unsigned, CastKind::Date, CastKind::Decimal,
            CastKind::Char, CastKind::Binary, CastKind::NationalChar, CastKind::Json,
            CastKind::Double, CastKind::Real, CastKind::Year, CastKind::Point,
            CastKind::LineString, CastKind::Polygon, CastKind::MultiPoint,
            CastKind::MultiLineString, CastKind::MultiPolygon, CastKind::GeometryCollection => null,
        };
        if ($maximum === null || $target->length === null || (int) $target->length <= $maximum) {
            return null;
        }
        $precision = (int) $target->length;
        if ($target->kind === CastKind::Float) {
            $precision = 0;
            foreach (str_split(explode('.', $target->length)[0]) as $digit) {
                $precision = ($precision * 10 + (int) $digit) % 4294967296;
            }
            if ($precision >= 2147483648) {
                $precision -= 4294967296;
            }
        }

        return new TooBigPrecision($precision, 'CAST', $maximum);
    }

    /**
     * Answers the form the server refuses while it parses a cast to an array, or null when the type is one a multi-valued index takes.
     *
     * A multi-valued index takes no character set, no BLOB, and none of JSON, the floating-point
     * types, YEAR and the spatial types; the parse stops there, in any statement. A cast to
     * another type is refused outside a functional index when the expression is resolved
     * (ARRAY_OUTSIDE_INDEX), which these facts do not tell (verified on a live 8.4 server).
     */
    public function array(CastTarget $target): ?string
    {
        $kind = $target->kind;
        if ($target->charset !== null || $kind === CastKind::NationalChar) {
            return 'specifying charset for multi-valued index';
        }

        return match ($kind) {
            CastKind::Char, CastKind::Binary => $target->length === null ? 'CAST-ing data to array of char/binary BLOBs' : null,
            CastKind::Real => 'CAST-ing data to array of DOUBLE',
            CastKind::MultiLineString => 'CAST-ing data to array of MULTILINESTRING>',
            CastKind::Json, CastKind::Double, CastKind::Float, CastKind::Year, CastKind::Point, CastKind::LineString, CastKind::Polygon, CastKind::MultiPoint, CastKind::MultiPolygon, CastKind::GeometryCollection => 'CAST-ing data to array of ' . $kind->value,
            CastKind::Signed, CastKind::Unsigned, CastKind::Date, CastKind::Time, CastKind::DateTime, CastKind::Decimal => null,
        };
    }

}
