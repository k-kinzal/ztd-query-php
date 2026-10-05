<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * The target type of CAST, CONVERT and JSON_VALUE ... RETURNING: a closed set of kinds with length, scale and character set.
 *
 * `SIGNED INTEGER` is `SIGNED` and `UNSIGNED INTEGER` is `UNSIGNED`;
 * `DOUBLE PRECISION` is `DOUBLE`. The server keeps one cast type value with
 * a target kind, and so does this class.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html#function_cast.
 *
 * @visibility public
 * @example Reading a cast target
 *     $target = new \SqlSemantics\Platform\MySql\Statement\Type\CastTarget(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Decimal, '10', '2');
 *     [$target->name(), $target->length, $target->scale] // => ['DECIMAL', '10', '2']
 */
final class CastTarget implements TypeName
{
    use Snapshot;

    /**
     * @param CastKind $kind The target kind
     * @param string|null $length The length, precision or fractional seconds precision exactly as written
     * @param string|null $scale The scale of DECIMAL exactly as written; requires a length
     * @param CharsetAttribute|null $charset The character set attribute of CHAR; BINARY alone for a national CHAR is not written
     * @param OptionalWords $words Whether the optional words are written: INT after SIGNED and UNSIGNED, PRECISION after DOUBLE, NATIONAL CHAR for NCHAR
     */
    public function __construct(public readonly CastKind $kind, public readonly ?string $length = null, public readonly ?string $scale = null, public readonly ?CharsetAttribute $charset = null, public readonly OptionalWords $words = OptionalWords::Omitted)
    {
        Check::input($words === OptionalWords::Omitted || in_array($kind, [CastKind::Signed, CastKind::Unsigned, CastKind::Double, CastKind::NationalChar], true), 'Only SIGNED, UNSIGNED, DOUBLE and NCHAR have optional words.');
        Check::input($length === null || preg_match('/\A(?:[0-9]+\.?[0-9]*|\.[0-9]+)\z/', $length) === 1, 'A length is an unsigned number.');
        Check::input($length === null || in_array($kind, [CastKind::Binary, CastKind::Char, CastKind::NationalChar, CastKind::Time, CastKind::DateTime, CastKind::Decimal, CastKind::Float], true), 'The target kind takes no length.');
        Check::input($scale === null || ($kind === CastKind::Decimal && $length !== null && preg_match('/\A[0-9]+\z/', $scale) === 1), 'Only DECIMAL takes a scale, written after a precision.');
        Check::input($charset === null || $kind === CastKind::Char, 'Only CHAR takes a character set attribute.');
    }

    /**
     * Names the target by its keyword.
     */
    public function name(): string
    {
        return $this->kind->value;
    }

    /**
     * Writes the keyword, the length and scale, and the character set attribute.
     */
    public function render(Output $out): void
    {
        if ($this->words === OptionalWords::Written) {
            $out->keyword(...match ($this->kind) {
                CastKind::NationalChar => ['NATIONAL', 'CHAR'],
                CastKind::Double => ['DOUBLE', 'PRECISION'],
                CastKind::Signed, CastKind::Unsigned => [$this->kind->value, 'INT'],
                CastKind::Binary, CastKind::Char, CastKind::Date, CastKind::Time, CastKind::DateTime, CastKind::Decimal, CastKind::Json, CastKind::Year,
                CastKind::Real, CastKind::Float, CastKind::Point, CastKind::LineString, CastKind::Polygon, CastKind::MultiPoint, CastKind::MultiLineString,
                CastKind::MultiPolygon, CastKind::GeometryCollection => [$this->kind->value],
            });
        } else {
            $out->keyword($this->kind->value);
        }
        if ($this->length !== null) {
            $out->glue()->symbol('(')->spelled($this->length);
            if ($this->scale !== null) {
                $out->symbol(',')->spelled($this->scale);
            }
            $out->symbol(')');
        }
        $out->node($this->charset);
    }
}
