<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Call;

use SqlSemantics\Diagnostic\InvariantViolation;
use SqlSemantics\Platform\MySql\Statement\Type\Binary;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Decimal;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Enumeration;
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
use SqlSemantics\Statement\Type\TypeDescriptor;

/**
 * The classes of MySQL data types that decide the result type of a function or of a type aggregation.
 *
 * Rule: MYSQL-CALL-TYPE-CLASS-001. Every MySQL type descriptor belongs to one
 * class: the integer types with BOOL and SERIAL, DECIMAL, the approximate
 * types, the character types with ENUM and SET, the binary string types,
 * DATE, TIME, DATETIME with TIMESTAMP, YEAR, JSON, the spatial types, BIT and
 * VECTOR. A cast target belongs to the class of the type it converts to. A
 * class names the descriptor a result of the class has when only the class
 * is known: BIGINT, DECIMAL, DOUBLE, VARCHAR, VARBINARY, DATE, TIME,
 * DATETIME, YEAR, JSON, GEOMETRY, BIT, VECTOR, and BIGINT UNSIGNED.
 * Terminates: one dispatch on the descriptor.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/data-types.html,
 * https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
enum TypeClass
{
    case Integer;
    case Unsigned;
    case Decimal;
    case Floating;
    case Character;
    case Binary;
    case Date;
    case Time;
    case DateTime;
    case Year;
    case Json;
    case Spatial;
    case Bit;
    case Vector;

    /**
     * Answers the class of a descriptor.
     */
    public static function of(TypeDescriptor $descriptor): self
    {
        return match (true) {
            $descriptor instanceof Integral => in_array(NumericModifier::Unsigned, $descriptor->modifiers, true) ? self::Unsigned : self::Integer,
            $descriptor instanceof Decimal => self::Decimal,
            $descriptor instanceof Floating => self::Floating,
            $descriptor instanceof Character, $descriptor instanceof Enumeration => self::Character,
            $descriptor instanceof Binary => self::Binary,
            $descriptor instanceof Temporal => self::temporal($descriptor->kind),
            $descriptor instanceof Spatial => self::Spatial,
            $descriptor instanceof Elementary => self::elementary($descriptor->kind),
            $descriptor instanceof CastTarget => self::cast($descriptor->kind),
            default => self::foreign(),
        };
    }

    /**
     * Answers the class of a temporal kind.
     */
    public static function temporal(TemporalKind $kind): self
    {
        return match ($kind) {
            TemporalKind::Date => self::Date,
            TemporalKind::Time => self::Time,
            TemporalKind::Timestamp, TemporalKind::DateTime => self::DateTime,
            TemporalKind::Year => self::Year,
        };
    }

    /**
     * Answers the class of a type written as one keyword.
     */
    public static function elementary(ElementaryKind $kind): self
    {
        return match ($kind) {
            ElementaryKind::Boolean, ElementaryKind::Serial => self::Integer,
            ElementaryKind::Json => self::Json,
            ElementaryKind::Bit => self::Bit,
            ElementaryKind::Vector => self::Vector,
        };
    }

    /**
     * Answers the class of the result of a cast.
     */
    public static function cast(CastKind $kind): self
    {
        return match ($kind) {
            CastKind::Binary => self::Binary,
            CastKind::Char, CastKind::NationalChar => self::Character,
            CastKind::Signed => self::Integer,
            CastKind::Unsigned => self::Unsigned,
            CastKind::Date => self::Date,
            CastKind::Time => self::Time,
            CastKind::DateTime => self::DateTime,
            CastKind::Decimal => self::Decimal,
            CastKind::Json => self::Json,
            CastKind::Year => self::Year,
            CastKind::Real, CastKind::Double, CastKind::Float => self::Floating,
            CastKind::Point, CastKind::LineString, CastKind::Polygon, CastKind::MultiPoint, CastKind::MultiLineString,
            CastKind::MultiPolygon, CastKind::GeometryCollection => self::Spatial,
        };
    }

    /**
     * Rejects a descriptor of another database, which no MySQL fact holds.
     *
     * @throws InvariantViolation Always
     */
    public static function foreign(): self
    {
        throw new InvariantViolation('A MySQL fact holds a MySQL type descriptor.');
    }

    /**
     * Tells whether the class holds numbers.
     */
    public function numeric(): bool
    {
        return in_array($this, [self::Integer, self::Unsigned, self::Decimal, self::Floating, self::Bit, self::Year], true);
    }

    /**
     * Tells whether the class holds dates or times.
     */
    public function temporalClass(): bool
    {
        return $this === self::Date || $this === self::Time || $this === self::DateTime;
    }

    /**
     * Answers the descriptor a result of the class has.
     */
    public function descriptor(): TypeDescriptor
    {
        return match ($this) {
            self::Integer => new Integral(IntegralKind::BigInt),
            self::Unsigned => new Integral(IntegralKind::BigInt, null, [NumericModifier::Unsigned]),
            self::Decimal => new Decimal(),
            self::Floating => new Floating(FloatingKind::Double),
            self::Character => new Character(CharacterKind::VarChar),
            self::Binary => new Binary(BinaryKind::VarBinary),
            self::Date => new Temporal(TemporalKind::Date),
            self::Time => new Temporal(TemporalKind::Time),
            self::DateTime => new Temporal(TemporalKind::DateTime),
            self::Year => new Temporal(TemporalKind::Year),
            self::Json => new Elementary(ElementaryKind::Json),
            self::Spatial => new Spatial(SpatialKind::Geometry),
            self::Bit => new Elementary(ElementaryKind::Bit),
            self::Vector => new Elementary(ElementaryKind::Vector),
        };
    }
}
