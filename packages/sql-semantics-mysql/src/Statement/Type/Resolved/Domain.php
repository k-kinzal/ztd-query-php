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
 * digits and signs for numbers; the result metadata reports it in bytes. Decimals is the number
 * of fractional digits, or NOT_FIXED when a floating-point number or string has none fixed.
 * Strings carry a collation and its coercibility; other values carry the binary collation.
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
     * Answers the same type with another collation and coercibility.
     */
    public function withCollation(Collation $collation, Coercibility $coercibility): self
    {
        return new self($this->kind, $this->field, $this->length, $this->decimals, $this->unsigned, $collation, $this->members, $coercibility);
    }

    /**
     * Answers the precision of a decimal: its digits, without the sign and the point.
     */
    public function precision(): int
    {
        return max(1, $this->length - ($this->decimals > 0 ? 1 : 0) - ($this->unsigned ? 0 : 1));
    }

    /**
     * Answers the display length in bytes, as the result metadata reports it.
     */
    public function byteLength(): int
    {
        return $this->kind === Kind::String || $this->kind->temporal() ? $this->length * $this->collation->charset->maxLength : $this->length;
    }

    /**
     * Names the type as the server lists it, without its attributes.
     */
    public function name(): string
    {
        return match ($this->field) {
            Field::Tiny => 'TINYINT',
            Field::Short => 'SMALLINT',
            Field::Int24 => 'MEDIUMINT',
            Field::Long => 'INT',
            Field::LongLong => 'BIGINT',
            Field::Decimal, Field::NewDecimal => 'DECIMAL',
            Field::Float => 'FLOAT',
            Field::Double => 'DOUBLE',
            Field::Null => 'NULL',
            Field::Timestamp => 'TIMESTAMP',
            Field::Date, Field::NewDate => 'DATE',
            Field::Time => 'TIME',
            Field::DateTime => 'DATETIME',
            Field::Year => 'YEAR',
            Field::Bit => 'BIT',
            Field::Vector => 'VECTOR',
            Field::Json => 'JSON',
            Field::Enum => 'ENUM',
            Field::Set => 'SET',
            Field::Geometry => 'GEOMETRY',
            Field::VarChar, Field::VarString => $this->collation->bytes() ? 'VARBINARY' : 'VARCHAR',
            Field::String => $this->collation->bytes() ? 'BINARY' : 'CHAR',
            Field::TinyBlob, Field::MediumBlob, Field::LongBlob, Field::Blob => $this->collation->bytes() ? 'BLOB' : 'TEXT',
        };
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
