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
 * MySQL 5.7 CAST AS JSON carries a 16 MiB utf8mb4_bin result bound, including
 * when character_set_results disables conversion. Verified through direct
 * and derived results with latin1 and utf8mb4 on Testcontainers 5.7.44.
 *
 * SIGNED and UNSIGNED are BIGINTs, as long as the operand but at most 21 characters in MySQL 5.6
 * and 5.7 (verified on live 5.6.51 and 5.7.44 servers). A legacy string conversion retains
 * up to 65 digits for arithmetic, separately from that reported width. DECIMAL takes the written precision and scale, 10 and 0 by
 * default; DOUBLE is a double; REAL is FLOAT under REAL_AS_FLOAT and DOUBLE otherwise;
 * FLOAT is a single unless its precision exceeds 24; the
 * temporal targets keep the fractional digits written; CHAR is a string in the connection
 * collation or the character set written, BINARY a binary string, both as long as written or as
 * the operand written as text; JSON is JSON; YEAR is four digits wide, five in MySQL 8.0 (verified
 * on a live 8.0.44 server). The spatial targets have the GEOMETRY field type and binary collation.
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
     * Resolves the conversion of an operand to a target.
     */
    public function cast(Domain $operand, CastTarget $target): Domain
    {
        $decimals = $target->length === null ? 0 : (int) $target->length;
        $fraction = $decimals > 0 ? $decimals + 1 : 0;

        return match ($target->kind) {
            CastKind::Signed => $this->integer($operand, false),
            CastKind::Unsigned => $this->integer($operand, true),
            CastKind::Decimal => $this->decimal($target),
            CastKind::Double => Domain::double(23),
            CastKind::Real => $this->settings->realAsFloat ? $this->float($target) : Domain::double(23),
            CastKind::Float => $this->float($target),
            CastKind::Date => new Domain(Kind::Date, Field::Date, 10),
            CastKind::Time => new Domain(Kind::Time, Field::Time, 10 + $fraction, $decimals),
            CastKind::DateTime => new Domain(Kind::DateTime, Field::DateTime, 19 + $fraction, $decimals),
            CastKind::Year => new Domain(Kind::Year, Field::Year, $this->release === GrammarRelease::MySql8044 ? 5 : 4, 0, true),
            CastKind::Char, CastKind::NationalChar => $this->string($operand, $target, $this->collation($target)),
            CastKind::Binary => $this->string($operand, $target, Collation::binary()),
            CastKind::Json => $this->release === GrammarRelease::MySql5744 ? new Domain(Kind::Json, Field::Json, 16777216, 0, false, Collation::known('utf8mb4_bin')) : JsonResults::json($this->release),
            CastKind::Point, CastKind::LineString, CastKind::Polygon, CastKind::MultiPoint, CastKind::MultiLineString,
            CastKind::MultiPolygon, CastKind::GeometryCollection => new Domain(Kind::String, Field::Geometry, 4294967295, 0, false, Collation::binary(), [], Coercibility::Coercible),
        };
    }

    /**
     * Resolves each entry of a multi-valued index separately from its JSON document.
     * An implicit character target uses the JSON binary collation.
     */
    public function array(?Domain $operand, CastTarget $target): Domain
    {
        $domain = $this->cast($operand ?? JsonResults::json($this->release), $target);

        return $target->kind === CastKind::Char && $target->charset === null
            ? $domain->withCollation(Collation::known('utf8mb4_0900_bin'), $domain->coercibility)
            : $domain;
    }

    /**
     * Resolves an integer conversion, retaining legacy string precision for arithmetic.
     */
    public function integer(Domain $operand, bool $unsigned): Domain
    {
        $legacy = $this->release === GrammarRelease::MySql5651 || $this->release === GrammarRelease::MySql5744;
        $display = $legacy ? min($operand->length, 21) : 21;
        if ($legacy && $operand->kind === Kind::String) {
            return new Domain(Kind::Integer, Field::LongLong, min(65, $operand->length) + (int) !$unsigned, 0, $unsigned, null, [], Coercibility::Numeric, $display);
        }

        return Domain::integer(Field::LongLong, $display, $unsigned);
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
    public function returning(?CastTarget $target): Domain
    {
        $text = Collation::known('utf8mb4_0900_bin');
        if ($target === null) {
            return Domain::string(512, $text, Field::VarString, Coercibility::Coercible);
        }
        $collation = match ($target->kind) {
            CastKind::Char => $target->charset === null ? $text : $this->collation($target),
            CastKind::NationalChar => $this->collation($target),
            CastKind::Binary => Collation::binary(),
            CastKind::Signed, CastKind::Unsigned, CastKind::Date, CastKind::Time, CastKind::DateTime, CastKind::Decimal, CastKind::Json, CastKind::Year, CastKind::Real, CastKind::Double, CastKind::Float,
            CastKind::Point, CastKind::LineString, CastKind::Polygon, CastKind::MultiPoint, CastKind::MultiLineString, CastKind::MultiPolygon, CastKind::GeometryCollection => null,
        };
        if ($collation !== null) {
            return $this->returningText($target->length, $collation);
        }
        if ($target->kind === CastKind::Decimal && $target->length !== null && (int) $target->length === 0) {
            return Domain::decimal(10, (int) $target->scale);
        }
        $domain = $this->cast(Domain::null(), $target);
        if ($target->kind === CastKind::Year && $this->release === GrammarRelease::MySql8044) {
            return new Domain(Kind::Year, Field::Year, 5);
        }
        if (!$domain->kind->temporal()) {
            return $domain;
        }

        return new Domain($domain->kind, $domain->field, $domain->length, $domain->decimals, false, Collation::known('latin1_swedish_ci'));
    }

    /**
     * Separates JSON return value bounds from character-converted result metadata.
     */
    public function returningText(?string $width, Collation $collation): Domain
    {
        $length = $width === null ? null : (new \SqlSemantics\Platform\MySql\Rules\Expression\Conversion\JsonWidths())->of($width);
        \SqlSemantics\Diagnostic\Check::invariant($length !== null || $width === null, 'A JSON return width has a character domain.');
        if ($length === null) {
            return Domain::string(4294967295, $collation, Field::LongBlob, Coercibility::Coercible);
        }
        $bytes = ($length * $collation->charset->maxLength) % 4294967296;
        $characters = intdiv($bytes, $collation->charset->maxLength);
        $field = $bytes <= 65535 ? Field::VarString : ($bytes > 16777215 ? Field::LongBlob : Field::MediumBlob);
        return new Domain(Kind::String, $field, $characters, Domain::NOT_FIXED, false, $collation, [], Coercibility::Coercible, $field !== Field::VarString || $characters * $collation->charset->maxLength !== $bytes ? $bytes : null);
    }

    /**
     * Resolves DECIMAL: the precision and scale written, 10 and 0 by default.
     */
    public function decimal(CastTarget $target): Domain
    {
        return Domain::decimal((int) $target->length === 0 ? 10 : (int) $target->length, $target->scale === null ? 0 : (int) $target->scale);
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
     * Implicit BINARY casts retain floating-point metadata width; legacy CHAR casts do too.
     * Legacy integer display overrides also survive a text cast without narrowing its value bound.
     */
    public function string(Domain $operand, CastTarget $target, Collation $collation): Domain
    {
        $limit = self::stringLimit($target, $this->release);
        if ($limit === null && $operand->kind === Kind::Json && $this->release !== GrammarRelease::MySql5651 && $this->release !== GrammarRelease::MySql5744) {
            return Domain::string(4294967295, $collation, Field::LongBlob);
        }

        $length = $limit ?? $this->length($operand);
        if ($operand->kind === Kind::Json) {
            return Domain::string($length, $collation);
        }
        if ($collation->bytes() && $limit === null && $operand->kind === Kind::String) {
            $length *= $operand->collation->charset->maxLength;
        }

        $domain = (new Builtin\FormatResults())->sized($length, $collation, Coercibility::Implicit);

        return $this->reportedString($operand, $target, $domain);
    }

    /**
     * Resolves an explicit string width. Legacy releases interpret the upper half
     * of the unsigned 32-bit range as an unspecified bound; modern releases use
     * the upper half of the unsigned 64-bit range. The same rule controls
     * result metadata and runtime padding. Verified through native CHAR/BINARY casts.
     */
    public static function stringLimit(CastTarget $target, GrammarRelease $release): ?int
    {
        if ($target->length === null || !in_array($target->kind, [CastKind::Char, CastKind::NationalChar, CastKind::Binary], true)) {
            return null;
        }
        $length = (int) $target->length;
        if ($length > 4294967295 && (new \SqlSemantics\Platform\MySql\Rules\Expression\Conversion\JsonWidths())->of($target->length) !== null) {
            return null;
        }
        return $length >= 2147483648 && $length <= 4294967295 && in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? null : $length;
    }

    /**
     * Retains a numeric operand's versioned CAST metadata independently of the text value bound.
     */
    public function reportedString(Domain $operand, CastTarget $target, Domain $domain): Domain
    {
        $legacy = $this->release === GrammarRelease::MySql5651 || $this->release === GrammarRelease::MySql5744;
        $display = null;
        if (self::stringLimit($target, $this->release) === null) {
            $display = match (true) {
                $operand->kind === Kind::Double && ($legacy || $target->kind === CastKind::Binary) => $operand->metadataLength(),
                $operand->kind === Kind::Integer && $legacy => $operand->display,
                default => null,
            };
        }

        $display = $display === null ? null : $display * $domain->collation->charset->maxLength;

        return $display === null || $display === $domain->metadataLength() ? $domain : new Domain($domain->kind, $domain->field, $domain->length, $domain->decimals, $domain->unsigned, $domain->collation, $domain->members, $domain->coercibility, display: $display);
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
            CharsetForm::Named, CharsetForm::CharacterSet, CharsetForm::CharSet => $charset->charset === null ? $this->settings->connection : (Charset::named($charset->charset->value)?->defaultCollation($this->release) ?? $this->settings->connection),
        };
    }

    /**
     * Answers the length in characters of an operand written as text.
     */
    public function length(Domain $operand): int
    {
        return $operand->kind === Kind::Double ? 22 : ($operand->kind === Kind::Decimal ? $operand->metadataLength() : $operand->length);
    }
}
