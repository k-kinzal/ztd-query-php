<?php

declare(strict_types=1);

namespace MySqlMemory\Typing;

use MySqlMemory\Result\ColumnFlag;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain as Resolved;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * The resolved type of an expression or a column: what the server knows of its values before it reads any.
 *
 * It is the field type the protocol reports, whether an integer is UNSIGNED, the display length
 * in characters, the number of decimals, the collation of a string, and whether the value can
 * be NULL. The kind says how a value of the domain is held at run time.
 *
 * @visibility public
 * @example The domain of an integer column
 *     $domain = \MySqlMemory\Typing\Domain::integer(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Long, 11);
 *     [$domain->kind, $domain->field, $domain->unsigned] // => [\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Integer, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Long, false]
 */
final class Domain
{
    /**
     * Decimals of a floating-point value or a string that has no fixed scale.
     */
    public const NOT_FIXED = 31;

    /**
     * The collation of a string; binary for every other kind.
     */
    public readonly Collation $collation;

    /**
     * @param Kind $kind How a value is held at run time
     * @param Field $field The field type the protocol reports
     * @param int $length The display length in characters
     * @param int $decimals The number of decimals, or NOT_FIXED
     * @param bool $unsigned Whether an integer or decimal is UNSIGNED
     * @param Collation|null $collation The collation of a string; binary for every other kind when null
     * @param bool $nullable Whether a value can be NULL
     * @param list<string> $members The members of an ENUM or SET, in declared order
     * @param Coercibility $coercibility How strongly the collation of a string holds against another
     * @param bool $numericBytes Whether the bytes of a binary string read as the integer they spell in a numeric context, as for a hexadecimal or bit literal
     * @param int|null $display The display width a column of an integer type reports for itself, when it is narrower than the length
     */
    public function __construct(
        public readonly Kind $kind,
        public readonly Field $field,
        public readonly int $length = 0,
        public readonly int $decimals = 0,
        public readonly bool $unsigned = false,
        ?Collation $collation = null,
        public readonly bool $nullable = true,
        public readonly array $members = [],
        public readonly Coercibility $coercibility = Coercibility::Implicit,
        public readonly bool $numericBytes = false,
        public readonly ?int $display = null,
    ) {
        $this->collation = $collation ?? Collation::binary();
    }

    /**
     * Answers the same domain with bytes that read as the integer they spell in a numeric context.
     */
    public function withNumericBytes(bool $numericBytes = true): self
    {
        return new self($this->kind, $this->field, $this->length, $this->decimals, $this->unsigned, $this->collation, $this->nullable, $this->members, $this->coercibility, $numericBytes, $this->display);
    }

    /**
     * Creates the domain of a type SQL Semantics resolved.
     */
    public static function of(Resolved $type, bool $nullable): self
    {
        return new self($type->kind, $type->field, $type->length, $type->decimals, $type->unsigned, $type->collation, $nullable, $type->members, $type->coercibility, false, $type->display);
    }

    /**
     * Answers the type without its nullability, as SQL Semantics resolves it.
     */
    public function resolved(): Resolved
    {
        return new Resolved($this->kind, $this->field, $this->length, $this->decimals, $this->unsigned, $this->collation, $this->members, $this->coercibility, $this->display);
    }

    /**
     * Creates the domain of an integer of a field type.
     */
    public static function integer(Field $field = Field::LongLong, int $length = 21, bool $unsigned = false): self
    {
        return new self(Kind::Integer, $field, $length, 0, $unsigned, Collation::binary(), false);
    }

    /**
     * Creates the domain of an exact decimal of a precision and scale.
     */
    public static function decimal(int $precision, int $scale, bool $unsigned = false): self
    {
        return new self(Kind::Decimal, Field::NewDecimal, $precision + ($scale > 0 ? 1 : 0) + ($unsigned ? 0 : 1), $scale, $unsigned, Collation::binary(), false);
    }

    /**
     * Creates the domain of a double-precision number.
     */
    public static function double(int $length = 22, int $decimals = self::NOT_FIXED): self
    {
        return new self(Kind::Double, Field::Double, $length, $decimals, false, Collation::binary(), false);
    }

    /**
     * Creates the domain of a variable-length string of a collation.
     */
    public static function string(int $length, Collation $collation, Field $field = Field::VarString): self
    {
        return new self(Kind::String, $field, $length, self::NOT_FIXED, false, $collation, false);
    }

    /**
     * Creates the domain of NULL.
     */
    public static function null(): self
    {
        return new self(Kind::Null, Field::Null, 0, 0, false, Collation::binary(), true, [], Coercibility::Ignorable);
    }

    /**
     * Answers the same domain with another nullability.
     */
    public function withNullable(bool $nullable): self
    {
        return $nullable === $this->nullable ? $this : new self($this->kind, $this->field, $this->length, $this->decimals, $this->unsigned, $this->collation, $nullable, $this->members, $this->coercibility, $this->numericBytes, $this->display);
    }

    /**
     * Answers the same domain with another collation and coercibility.
     */
    public function withCollation(Collation $collation, Coercibility $coercibility): self
    {
        return new self($this->kind, $this->field, $this->length, $this->decimals, $this->unsigned, $collation, $this->nullable, $this->members, $coercibility, $this->numericBytes, $this->display);
    }

    /**
     * Answers the precision of a decimal: its digits, without the sign and the point.
     */
    public function precision(): int
    {
        return max(1, $this->length - ($this->decimals > 0 ? 1 : 0) - ($this->unsigned ? 0 : 1));
    }

    /**
     * Answers the display length in bytes, as the column definition reports it: the display width of a column of an integer type that declares one.
     */
    public function byteLength(): int
    {
        return $this->kind === Kind::String || $this->kind->temporal() ? $this->length * $this->collation->charset->maxLength : $this->display ?? $this->length;
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
        if ($this->collation === Collation::binary() && $this->kind !== Kind::Null) {
            $flags |= ColumnFlag::Binary->value;
        }
        if ($this->kind->numeric() && $this->field !== Field::Year) {
            $flags |= ColumnFlag::Numeric->value;
        }
        if ($this->field === Field::Enum) {
            $flags |= ColumnFlag::Enum->value;
        }
        if ($this->field === Field::Set) {
            $flags |= ColumnFlag::Set->value;
        }

        return $flags;
    }
}
