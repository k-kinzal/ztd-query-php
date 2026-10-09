<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\JsonResults;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;

/**
 * Resolves the type of CAST and CONVERT to a type.
 *
 * SIGNED and UNSIGNED are BIGINTs, as long as the operand but at most 21 characters in MySQL 5.6
 * and 5.7 (verified on live 5.6.51 and 5.7.44 servers); DECIMAL takes the written precision and scale, 10 and 0 by
 * default; DOUBLE and REAL are doubles, FLOAT a single unless its precision exceeds 24; the
 * temporal targets keep the fractional digits written; CHAR is a string in the connection
 * collation or the character set written, BINARY a binary string, both as long as written or as
 * the operand written as text; JSON is JSON; YEAR is four digits wide, five in MySQL 8.0 (verified
 * on a live 8.0.44 server). The spatial targets have no resolved type.
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

        $legacy = $this->release === GrammarRelease::MySql5651 || $this->release === GrammarRelease::MySql5744;
        $integral = $legacy ? min($operand->length, 21) : 21;

        return match ($target->kind) {
            CastKind::Signed => Domain::integer(Field::LongLong, $integral),
            CastKind::Unsigned => Domain::integer(Field::LongLong, $integral, true),
            CastKind::Decimal => $this->decimal($target),
            CastKind::Double, CastKind::Real => Domain::double(23),
            CastKind::Float => $this->float($target),
            CastKind::Date => new Domain(Kind::Date, Field::Date, 10),
            CastKind::Time => new Domain(Kind::Time, Field::Time, 10 + $fraction, $decimals),
            CastKind::DateTime => new Domain(Kind::DateTime, Field::DateTime, 19 + $fraction, $decimals),
            CastKind::Year => new Domain(Kind::Year, Field::Year, $this->release === GrammarRelease::MySql8044 ? 5 : 4, 0, true),
            CastKind::Char, CastKind::NationalChar => $this->string($operand, $target, $this->collation($target)),
            CastKind::Binary => $this->string($operand, $target, Collation::binary()),
            CastKind::Json => JsonResults::json($this->release),
            CastKind::Point, CastKind::LineString, CastKind::Polygon, CastKind::MultiPoint, CastKind::MultiLineString,
            CastKind::MultiPolygon, CastKind::GeometryCollection => null,
        };
    }

    /**
     * Resolves the RETURNING type of JSON_VALUE: VARCHAR(512) in utf8mb4_0900_bin by default.
     *
     * CHAR without a character set is in utf8mb4_0900_bin, and CHAR or BINARY without a length is
     * a LONGTEXT or LONGBLOB; the strings are coercible. A date or time is sent in utf8mb4, so
     * its length counts four bytes a character; every other type is that of CAST (verified on a
     * live 8.4 server).
     * Source: https://dev.mysql.com/doc/refman/8.4/en/json-search-functions.html#function_json-value.
     */
    public function returning(?CastTarget $target): ?Domain
    {
        $text = Collation::known('utf8mb4_0900_bin');
        if ($target === null) {
            return Domain::string(512, $text, Field::VarString, Coercibility::Coercible);
        }
        $length = $target->length === null ? null : (int) $target->length;
        $collation = match ($target->kind) {
            CastKind::Char => $target->charset === null ? $text : $this->collation($target),
            CastKind::NationalChar => $this->collation($target),
            CastKind::Binary => Collation::binary(),
            CastKind::Signed, CastKind::Unsigned, CastKind::Date, CastKind::Time, CastKind::DateTime, CastKind::Decimal, CastKind::Json, CastKind::Year, CastKind::Real, CastKind::Double, CastKind::Float,
            CastKind::Point, CastKind::LineString, CastKind::Polygon, CastKind::MultiPoint, CastKind::MultiLineString, CastKind::MultiPolygon, CastKind::GeometryCollection => null,
        };
        if ($collation !== null) {
            return Domain::string($length ?? 4294967295, $collation, $length === null ? Field::LongBlob : Field::VarString, Coercibility::Coercible);
        }
        $domain = $this->cast(Domain::null(), $target);
        if ($domain === null || !$domain->kind->temporal()) {
            return $domain;
        }

        return new Domain($domain->kind, $domain->field, $domain->length, $domain->decimals, false, $text);
    }

    /**
     * Resolves DECIMAL: the precision and scale written, 10 and 0 by default.
     */
    public function decimal(CastTarget $target): Domain
    {
        return Domain::decimal($target->length === null ? 10 : (int) $target->length, $target->scale === null ? 0 : (int) $target->scale);
    }

    /**
     * Resolves FLOAT: a single, or a double when the precision written exceeds 24, both 23 characters wide as DOUBLE and REAL are (verified on live 8.0, 8.4 and 9.1 servers).
     */
    public function float(CastTarget $target): Domain
    {
        return $target->length !== null && (int) $target->length > 24 ? Domain::double(23) : new Domain(Kind::Double, Field::Float, 23, Domain::NOT_FIXED);
    }

    /**
     * Resolves CHAR or BINARY in a collation: a string as long as written, or as the operand written as text, BINARY counting the bytes of a string; a JSON value without a length is a LONGTEXT or LONGBLOB from MySQL 8.0. A string longer than 65535 bytes is a MEDIUMBLOB or LONGBLOB (verified on live 5.7.44, 8.0.44 and 8.4 servers).
     */
    public function string(Domain $operand, CastTarget $target, Collation $collation): Domain
    {
        if ($target->length === null && $operand->kind === Kind::Json && $this->release !== GrammarRelease::MySql5651 && $this->release !== GrammarRelease::MySql5744) {
            return Domain::string(4294967295, $collation, Field::LongBlob);
        }

        $length = $target->length === null ? $this->length($operand) : (int) $target->length;
        if ($operand->kind === Kind::Json) {
            return Domain::string($length, $collation);
        }
        if ($collation->bytes() && $target->length === null && $operand->kind === Kind::String) {
            $length *= $operand->collation->charset->maxLength;
        }

        return (new Builtin\FormatResults())->sized($length, $collation, Coercibility::Implicit);
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
