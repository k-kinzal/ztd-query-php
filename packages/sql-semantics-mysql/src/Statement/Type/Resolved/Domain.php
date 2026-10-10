<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type\Resolved;

use SqlSemantics\Platform\MySql\Statement\Type\Binary;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Decimal;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Floating;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\SpatialKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Spatial;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;
use SqlSemantics\Platform\MySql\Statement\Type\TypeName;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\TypeDescriptor;

/**
 * The type the server resolves for a value: its kind, the column type it reports, and the attributes that come with it.
 *
 * The length is the display length, counted in characters for strings and temporal values and in
 * digits and signs for numbers. TEXT/BLOB fields retain a byte bound in the same attribute;
 * byteLength() gives the bound used in expression typing, while metadataLength() gives the
 * unconverted result column length. A computed blob can retain its character bound
 * in length and its separate result width in display, as GROUP_CONCAT does. Decimals is the number
 * of fractional digits, or NOT_FIXED when a floating-point number or string has none fixed.
 * Strings carry a collation and its coercibility; other values carry the binary collation.
 * A column of an integer type can declare a display width narrower than its type: the result
 * metadata reports that width for the column itself, while an expression over the column sees
 * the length of the whole type.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/numeric-type-attributes.html.
 *
 * @visibility public
 * @example Reading the display length of DECIMAL(5,2)
 *     \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::decimal(5, 2)->length // => 7
 */
final class Domain implements TypeDescriptor
{
    use Snapshot;

    /**
     * The decimals of a value without a fixed number of fractional digits.
     */
    public const NOT_FIXED = 31;

    private const INTEGRALS = [1 => IntegralKind::TinyInt, 2 => IntegralKind::SmallInt, 9 => IntegralKind::MediumInt, 3 => IntegralKind::Int];

    private const BINARIES = [254 => BinaryKind::Binary, 249 => BinaryKind::TinyBlob, 252 => BinaryKind::Blob, 250 => BinaryKind::MediumBlob, 251 => BinaryKind::LongBlob];

    private const CHARACTERS = [254 => CharacterKind::Char, 249 => CharacterKind::TinyText, 252 => CharacterKind::Text, 250 => CharacterKind::MediumText, 251 => CharacterKind::LongText];

    /**
     * The collation of a string; the binary collation for every other kind.
     */
    public readonly Collation $collation;

    /**
     * @param Kind $kind The class of values
     * @param Field $field The column type the result metadata reports
     * @param int $length The display length
     * @param int $decimals The fractional digits, or NOT_FIXED
     * @param bool $unsigned Whether a number is unsigned
     * @param Collation|null $collation The collation of a string; binary when null
     * @param list<string> $members The members of an ENUM or SET, in declared order
     * @param Coercibility $coercibility How strongly the collation holds
     * @param int|null $display The unconverted metadata width when it differs from the type bound, including integer display widths and partial multibyte string limits
     */
    public function __construct(
        public readonly Kind $kind,
        public readonly Field $field,
        public readonly int $length = 0,
        public readonly int $decimals = 0,
        public readonly bool $unsigned = false,
        ?Collation $collation = null,
        public readonly array $members = [],
        public readonly Coercibility $coercibility = Coercibility::Implicit,
        public readonly ?int $display = null,
    ) {
        $this->collation = $collation ?? Collation::binary();
    }

    /**
     * Creates the type of an integer.
     */
    public static function integer(Field $field = Field::LongLong, int $length = 21, bool $unsigned = false): self
    {
        return new self(Kind::Integer, $field, $length, 0, $unsigned, null, [], Coercibility::Numeric);
    }

    /**
     * Creates the type of an exact decimal of a precision and scale.
     */
    public static function decimal(int $precision, int $scale, bool $unsigned = false): self
    {
        return new self(Kind::Decimal, Field::NewDecimal, $precision + ($scale > 0 ? 1 : 0) + ($unsigned ? 0 : 1), $scale, $unsigned, null, [], Coercibility::Numeric);
    }

    /**
     * Creates the type of a double-precision number.
     */
    public static function double(int $length = 22, int $decimals = self::NOT_FIXED): self
    {
        return new self(Kind::Double, Field::Double, $length, $decimals, false, null, [], Coercibility::Numeric);
    }

    /**
     * Creates the type of a string of a collation.
     */
    public static function string(int $length, Collation $collation, Field $field = Field::VarString, Coercibility $coercibility = Coercibility::Implicit): self
    {
        return new self(Kind::String, $field, $length, self::NOT_FIXED, false, $collation, [], $coercibility);
    }

    /**
     * Creates the type of NULL.
     */
    public static function null(): self
    {
        return new self(Kind::Null, Field::Null, 0, 0, false, null, [], Coercibility::Ignorable);
    }

    /**
     * Creates the type of a column of an integer type: as long as the type, reporting its display width for itself when that is narrower.
     *
     * The type is as long as its widest value with its sign: 4, 6, 9, 11 and 20 for TINYINT,
     * SMALLINT, MEDIUMINT, INT and BIGINT, one fewer when unsigned except for BIGINT.
     */
    public static function column(Field $field, int $width, bool $unsigned = false): self
    {
        $length = match ($field) {
            Field::Tiny => $unsigned ? 3 : 4,
            Field::Short => $unsigned ? 5 : 6,
            Field::Int24 => $unsigned ? 8 : 9,
            Field::Long => $unsigned ? 10 : 11,
            Field::LongLong, Field::Decimal, Field::Float, Field::Double, Field::Null, Field::Timestamp, Field::Date, Field::Time, Field::DateTime, Field::Year, Field::NewDate,
            Field::VarChar, Field::Bit, Field::Vector, Field::Json, Field::NewDecimal, Field::Enum, Field::Set, Field::TinyBlob, Field::MediumBlob, Field::LongBlob, Field::Blob,
            Field::VarString, Field::String, Field::Geometry => 20,
        };

        return new self(Kind::Integer, $field, max($width, $length), 0, $unsigned, null, [], Coercibility::Numeric, $width < $length ? $width : null);
    }

    /**
     * Answers the type of the value a column holds, as an expression over it sees it: without the display width of the column.
     */
    public function value(): self
    {
        return $this->display === null || $this->field->blob() ? $this : new self($this->kind, $this->field, $this->length, $this->decimals, $this->unsigned, $this->collation, $this->members, $this->coercibility);
    }

    /**
     * Answers the same type with another collation and coercibility.
     */
    public function withCollation(Collation $collation, Coercibility $coercibility): self
    {
        return new self($this->kind, $this->field, $this->length, $this->decimals, $this->unsigned, $collation, $this->members, $coercibility, $this->field->blob() ? $this->display : null);
    }

    /**
     * Answers the precision of a decimal: its digits, without the sign and the point.
     */
    public function precision(): int
    {
        return max(1, $this->length - ($this->decimals > 0 ? 1 : 0) - ($this->unsigned ? 0 : 1));
    }

    /**
     * Answers the byte bound used when resolving expressions over this value.
     */
    public function byteLength(): int
    {
        return $this->kind === Kind::String || $this->kind->temporal() ? $this->length * $this->collation->charset->maxLength : $this->length;
    }

    /**
     * Answers the column length in bytes when the result character set performs no conversion.
     *
     * TEXT/BLOB fields already carry byte bounds. These differ from the collation-expanded
     * bounds used by functions such as HEX and GTID_SUBTRACT. Integer columns retain their
     * declared display widths; byte-limited strings retain partial-character bounds. Verified through result metadata on live MySQL servers.
     */
    public function metadataLength(): int
    {
        return $this->display ?? ($this->field->blob() ? $this->length : $this->byteLength());
    }

    /**
     * Names the type as the server lists it, without its attributes.
     */
    public function name(): string
    {
        return $this->field->typeName($this->collation->bytes());
    }

    /**
     * Answers the type name that declares a value of the same class, without the attributes of this type.
     */
    public function declared(): TypeName
    {
        $unsigned = $this->unsigned ? [NumericModifier::Unsigned] : [];

        return match ($this->kind) {
            Kind::Integer => new Integral(self::INTEGRALS[$this->field->value] ?? IntegralKind::BigInt, null, $unsigned),
            Kind::Decimal => new Decimal(null, null, $unsigned),
            Kind::Double => new Floating($this->field === Field::Float ? FloatingKind::Float : FloatingKind::Double),
            Kind::String, Kind::Null => $this->text(),
            Kind::Date => new Temporal(TemporalKind::Date),
            Kind::Time => new Temporal(TemporalKind::Time),
            Kind::DateTime => new Temporal($this->field === Field::Timestamp ? TemporalKind::Timestamp : TemporalKind::DateTime),
            Kind::Year => new Temporal(TemporalKind::Year),
            Kind::Json => new Elementary(ElementaryKind::Json),
            Kind::Bit => new Elementary(ElementaryKind::Bit),
        };
    }

    /**
     * Answers the type name that declares a string of the same field and character set class.
     */
    public function text(): TypeName
    {
        if ($this->field === Field::Geometry) {
            return new Spatial(SpatialKind::Geometry);
        }
        if ($this->field === Field::Vector) {
            return new Elementary(ElementaryKind::Vector);
        }
        if ($this->collation->bytes()) {
            return new Binary(self::BINARIES[$this->field->value] ?? BinaryKind::VarBinary);
        }

        return new Character(self::CHARACTERS[$this->field->value] ?? CharacterKind::VarChar);
    }

}
