<?php

declare(strict_types=1);

namespace MySqlMemory\Typing;

use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\FieldType;

/**
 * The resolved type of an expression or a column: what the server knows of its values before it reads any.
 *
 * It is the field type the protocol reports, whether an integer is UNSIGNED, the display length
 * in characters, the number of decimals, the collation of a string, and whether the value can
 * be NULL. The kind says how a value of the domain is held at run time.
 *
 * @visibility public
 * @example The domain of an integer column
 *     $domain = \MySqlMemory\Typing\Domain::integer(FieldType::Long, 11);
 *     [$domain->kind, $domain->field, $domain->unsigned] // => [\MySqlMemory\Typing\Kind::Integer, \MySqlMemory\Result\FieldType::Long, false]
 */
final class Domain
{
    /**
     * Decimals of a floating-point value or a string that has no fixed scale.
     */
    public const NOT_FIXED = 31;

    /**
     * @param Kind $kind How a value is held at run time
     * @param FieldType $field The field type the protocol reports
     * @param int $length The display length in characters
     * @param int $decimals The number of decimals, or NOT_FIXED
     * @param bool $unsigned Whether an integer or decimal is UNSIGNED
     * @param Collation $collation The collation of a string; binary for every other kind
     * @param bool $nullable Whether a value can be NULL
     * @param list<string> $members The members of an ENUM or SET, in declared order
     * @param Coercibility $coercibility How strongly the collation of a string holds against another
     */
    public function __construct(
        public readonly Kind $kind,
        public readonly FieldType $field,
        public readonly int $length = 0,
        public readonly int $decimals = 0,
        public readonly bool $unsigned = false,
        public readonly Collation $collation = Collation::Binary,
        public readonly bool $nullable = true,
        public readonly array $members = [],
        public readonly Coercibility $coercibility = Coercibility::Implicit,
    ) {
    }

    /**
     * Creates the domain of an integer of a field type.
     */
    public static function integer(FieldType $field = FieldType::LongLong, int $length = 21, bool $unsigned = false): self
    {
        return new self(Kind::Integer, $field, $length, 0, $unsigned, Collation::Binary, false);
    }

    /**
     * Creates the domain of an exact decimal of a precision and scale.
     */
    public static function decimal(int $precision, int $scale, bool $unsigned = false): self
    {
        return new self(Kind::Decimal, FieldType::NewDecimal, $precision + ($scale > 0 ? 1 : 0) + ($unsigned ? 0 : 1), $scale, $unsigned, Collation::Binary, false);
    }

    /**
     * Creates the domain of a double-precision number.
     */
    public static function double(int $length = 22, int $decimals = self::NOT_FIXED): self
    {
        return new self(Kind::Double, FieldType::Double, $length, $decimals, false, Collation::Binary, false);
    }

    /**
     * Creates the domain of a variable-length string of a collation.
     */
    public static function string(int $length, Collation $collation, FieldType $field = FieldType::VarString): self
    {
        return new self(Kind::String, $field, $length, self::NOT_FIXED, false, $collation, false);
    }

    /**
     * Creates the domain of NULL.
     */
    public static function null(): self
    {
        return new self(Kind::Null, FieldType::Null, 0, 0, false, Collation::Binary, true, [], Coercibility::Ignorable);
    }

    /**
     * Answers the same domain with another nullability.
     */
    public function withNullable(bool $nullable): self
    {
        return $nullable === $this->nullable ? $this : new self($this->kind, $this->field, $this->length, $this->decimals, $this->unsigned, $this->collation, $nullable, $this->members, $this->coercibility);
    }

    /**
     * Answers the same domain with another collation and coercibility.
     */
    public function withCollation(Collation $collation, Coercibility $coercibility): self
    {
        return new self($this->kind, $this->field, $this->length, $this->decimals, $this->unsigned, $collation, $this->nullable, $this->members, $coercibility);
    }

    /**
     * Answers the precision of a decimal: its digits, without the sign and the point.
     */
    public function precision(): int
    {
        return max(1, $this->length - ($this->decimals > 0 ? 1 : 0) - ($this->unsigned ? 0 : 1));
    }

    /**
     * Answers the display length in bytes, as the column definition reports it.
     */
    public function byteLength(): int
    {
        return $this->kind === Kind::String || $this->kind->temporal() ? $this->length * $this->collation->charset()->maxLength() : $this->length;
    }

    /**
     * Answers the column definition flags of the domain.
     */
    public function flags(): int
    {
        $flags = $this->nullable ? 0 : ColumnFlag::NotNull->value;
        if ($this->unsigned) {
            $flags |= ColumnFlag::Unsigned->value;
        }
        if ($this->collation === Collation::Binary && $this->kind !== Kind::Null) {
            $flags |= ColumnFlag::Binary->value;
        }
        if (in_array($this->field, [FieldType::Blob, FieldType::TinyBlob, FieldType::MediumBlob, FieldType::LongBlob, FieldType::Json], true)) {
            $flags |= ColumnFlag::Blob->value;
        }
        if ($this->kind->numeric() && $this->field !== FieldType::Year) {
            $flags |= ColumnFlag::Numeric->value;
        }
        if ($this->field === FieldType::Enum) {
            $flags |= ColumnFlag::Enum->value;
        }
        if ($this->field === FieldType::Set) {
            $flags |= ColumnFlag::Set->value;
        }

        return $flags;
    }
}
