<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;

/**
 * Resolves the type of CAST and CONVERT to a type.
 *
 * SIGNED and UNSIGNED are BIGINTs; DECIMAL takes the written precision and scale, 10 and 0 by
 * default; DOUBLE and REAL are doubles, FLOAT a single unless its precision exceeds 24; the
 * temporal targets keep the fractional digits written; CHAR is a string in the connection
 * collation or the character set written, BINARY a binary string, both as long as written or as
 * the operand written as text; JSON is JSON. The spatial targets have no resolved type.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Casts
{
    /**
     * @param Settings $settings The session the conversion is resolved in
     * @param GrammarRelease $release The release whose character sets a target names
     */
    public function __construct(public readonly Settings $settings, public readonly GrammarRelease $release)
    {
    }

    /**
     * Resolves the conversion of an operand to a target, or answers null for a spatial target.
     */
    public function cast(Domain $operand, CastTarget $target): ?Domain
    {
        $decimals = $target->length === null ? 0 : (int) $target->length;
        $fraction = $decimals > 0 ? $decimals + 1 : 0;

        return match ($target->kind) {
            CastKind::Signed => Domain::integer(Field::LongLong, 21),
            CastKind::Unsigned => Domain::integer(Field::LongLong, 21, true),
            CastKind::Decimal => Domain::decimal($target->length === null ? 10 : (int) $target->length, $target->scale === null ? 0 : (int) $target->scale),
            CastKind::Double, CastKind::Real => Domain::double(22),
            CastKind::Float => $target->length !== null && (int) $target->length > 24 ? Domain::double(22) : new Domain(Kind::Double, Field::Float, 12, Domain::NOT_FIXED),
            CastKind::Date => new Domain(Kind::Date, Field::Date, 10),
            CastKind::Time => new Domain(Kind::Time, Field::Time, 10 + $fraction, $decimals),
            CastKind::DateTime => new Domain(Kind::DateTime, Field::DateTime, 19 + $fraction, $decimals),
            CastKind::Year => new Domain(Kind::Year, Field::Year, 4, 0, true),
            CastKind::Char, CastKind::NationalChar => Domain::string($target->length === null ? $this->length($operand) : (int) $target->length, $this->collation($target)),
            CastKind::Binary => Domain::string($target->length === null ? $this->length($operand) : (int) $target->length, Collation::binary()),
            CastKind::Json => new Domain(Kind::Json, Field::Json, 4294967295, Domain::NOT_FIXED, false, Collation::known('utf8mb4_bin')),
            CastKind::Point, CastKind::LineString, CastKind::Polygon, CastKind::MultiPoint, CastKind::MultiLineString,
            CastKind::MultiPolygon, CastKind::GeometryCollection => null,
        };
    }

    /**
     * Answers the collation of CHAR: the connection collation, utf8mb3 for NCHAR, or the character set written.
     */
    public function collation(CastTarget $target): Collation
    {
        if ($target->kind === CastKind::NationalChar) {
            return Collation::known('utf8mb3_general_ci');
        }
        $charset = $target->charset;
        if ($charset === null) {
            return $this->settings->connection;
        }

        return match ($charset->form) {
            CharsetForm::Ascii => Collation::known('ascii_general_ci'),
            CharsetForm::Unicode => Collation::known('utf8mb4_0900_ai_ci'),
            CharsetForm::Byte, CharsetForm::Binary => Collation::binary(),
            CharsetForm::Named, CharsetForm::CharacterSet => $charset->charset === null ? $this->settings->connection : (Charset::named($charset->charset->value)?->defaultCollation($this->release) ?? $this->settings->connection),
        };
    }

    /**
     * Answers the length in characters of an operand written as text.
     */
    public function length(Domain $operand): int
    {
        return $operand->kind === Kind::Double ? 22 : $operand->length;
    }
}
