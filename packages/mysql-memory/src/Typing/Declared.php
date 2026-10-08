<?php

declare(strict_types=1);

namespace MySqlMemory\Typing;

use MySqlMemory\Value\Encoding;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Binary;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute;
use SqlSemantics\Platform\MySql\Statement\Type\Decimal;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Enumeration;
use SqlSemantics\Platform\MySql\Statement\Type\Floating;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\EnumerationKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;
use SqlSemantics\Statement\Type\TypeDescriptor;

/**
 * Resolves a declared data type into the domain of the column it declares.
 *
 * A string type without a character set takes the default collation of its table; the display
 * lengths are those the server reports for columns of each type.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/data-types.html.
 *
 * @visibility MySqlMemory
 */
final class Declared
{
    /**
     * @param Collation $collation The collation of a string type that names none
     */
    public function __construct(public readonly Collation $collation, public readonly GrammarRelease $release = GrammarRelease::MySql847)
    {
    }

    /**
     * Resolves a type into a domain; a type the emulator does not hold is a long binary string.
     */
    public function domain(TypeDescriptor $type, ?Collation $collation = null): Domain
    {
        return match (true) {
            $type instanceof Integral => $this->integral($type),
            $type instanceof Decimal => Domain::decimal($type->precision === null ? 10 : (int) $type->precision, $type->scale === null ? 0 : (int) $type->scale, in_array(NumericModifier::Unsigned, $type->modifiers, true) || in_array(NumericModifier::Zerofill, $type->modifiers, true)),
            $type instanceof Floating => $this->floating($type),
            $type instanceof Character => $this->character($type, $collation),
            $type instanceof Binary => $this->binary($type),
            $type instanceof Temporal => $this->temporal($type),
            $type instanceof Enumeration => $this->enumeration($type, $collation),
            $type instanceof Elementary => $this->elementary($type),
            default => new Domain(Kind::String, Field::Blob, 4294967295, Domain::NOT_FIXED, false, Collation::binary()),
        };
    }

    /**
     * Resolves an integer type.
     */
    public function integral(Integral $type): Domain
    {
        $unsigned = in_array(NumericModifier::Unsigned, $type->modifiers, true) || in_array(NumericModifier::Zerofill, $type->modifiers, true);
        [$field, $length] = match ($type->kind) {
            IntegralKind::TinyInt => [Field::Tiny, $unsigned ? 3 : 4],
            IntegralKind::SmallInt => [Field::Short, $unsigned ? 5 : 6],
            IntegralKind::MediumInt => [Field::Int24, $unsigned ? 8 : 9],
            IntegralKind::Int => [Field::Long, $unsigned ? 10 : 11],
            IntegralKind::BigInt => [Field::LongLong, 20],
        };

        return Domain::integer($field, $type->width === null ? $length : (int) $type->width, $unsigned);
    }

    /**
     * Resolves FLOAT, REAL and DOUBLE, with or without a precision and scale.
     */
    public function floating(Floating $type): Domain
    {
        $single = $type->kind === FloatingKind::Float && ($type->precision === null || $type->scale !== null || (int) $type->precision <= 24);
        $unsigned = in_array(NumericModifier::Unsigned, $type->modifiers, true);
        $decimals = $type->scale === null ? Domain::NOT_FIXED : (int) $type->scale;
        $length = $type->scale !== null ? (int) $type->precision : ($single ? 12 : 22);

        return new Domain(Kind::Double, $single ? Field::Float : Field::Double, $length, $decimals, $unsigned);
    }

    /**
     * Resolves a character string type.
     */
    public function character(Character $type, ?Collation $collation): Domain
    {
        $collation = $this->charset($type->charset, $type->national ? Collation::known('utf8mb3_general_ci') : ($collation ?? $this->collation));
        [$field, $length] = match ($type->kind) {
            CharacterKind::Char => [Field::String, $type->length === null ? 1 : (int) $type->length],
            CharacterKind::VarChar, CharacterKind::CharVarying => [Field::VarString, (int) $type->length],
            CharacterKind::TinyText => [Field::Blob, 255],
            CharacterKind::Text => [Field::Blob, $type->length === null ? 65535 : $this->textLength((int) $type->length, $collation)],
            CharacterKind::MediumText, CharacterKind::Long, CharacterKind::LongVarChar, CharacterKind::LongCharVarying => [Field::Blob, 16777215],
            CharacterKind::LongText => [Field::Blob, 4294967295],
        };

        return Domain::string($length, $collation, $field);
    }

    /**
     * Answers the byte length of the smallest TEXT type that holds a number of characters.
     */
    public function textLength(int $characters, Collation $collation): int
    {
        $bytes = $characters * $collation->charset->maxLength;

        return $bytes <= 255 ? 255 : ($bytes <= 65535 ? 65535 : ($bytes <= 16777215 ? 16777215 : 4294967295));
    }

    /**
     * Resolves the collation a character set attribute names.
     */
    public function charset(?CharsetAttribute $attribute, Collation $collation): Collation
    {
        if ($attribute === null) {
            return $collation;
        }

        return match ($attribute->form) {
            CharsetForm::Ascii => Collation::known('ascii_general_ci'),
            CharsetForm::Unicode => Collation::known('utf8mb4_0900_ai_ci'),
            CharsetForm::Byte => Collation::binary(),
            CharsetForm::Binary => Collation::named($collation->charset->name . '_bin') ?? Collation::known('utf8mb4_bin'),
            CharsetForm::Named, CharsetForm::CharacterSet => $attribute->charset === null ? $collation : (Charset::named($attribute->charset->value)?->defaultCollation($this->release) ?? $collation),
        };
    }

    /**
     * Resolves a binary string type.
     */
    public function binary(Binary $type): Domain
    {
        [$field, $length] = match ($type->kind) {
            BinaryKind::Binary => [Field::String, $type->length === null ? 1 : (int) $type->length],
            BinaryKind::VarBinary => [Field::VarString, (int) $type->length],
            BinaryKind::TinyBlob => [Field::Blob, 255],
            BinaryKind::Blob => [Field::Blob, $type->length === null ? 65535 : $this->textLength((int) $type->length, Collation::binary())],
            BinaryKind::MediumBlob, BinaryKind::LongVarBinary => [Field::Blob, 16777215],
            BinaryKind::LongBlob => [Field::Blob, 4294967295],
        };

        return Domain::string($length, Collation::binary(), $field);
    }

    /**
     * Resolves a date, time, datetime, timestamp or year type.
     */
    public function temporal(Temporal $type): Domain
    {
        $decimals = $type->precision === null ? 0 : (int) $type->precision;
        $fraction = $decimals > 0 ? $decimals + 1 : 0;

        return match ($type->kind) {
            TemporalKind::Date => new Domain(Kind::Date, Field::Date, 10),
            TemporalKind::Time => new Domain(Kind::Time, Field::Time, 10 + $fraction, $decimals),
            TemporalKind::DateTime => new Domain(Kind::DateTime, Field::DateTime, 19 + $fraction, $decimals),
            TemporalKind::Timestamp => new Domain(Kind::DateTime, Field::Timestamp, 19 + $fraction, $decimals),
            TemporalKind::Year => new Domain(Kind::Year, Field::Year, 4, 0, true),
        };
    }

    /**
     * Resolves ENUM and SET, whose members are held in the character set of the column.
     */
    public function enumeration(Enumeration $type, ?Collation $collation): Domain
    {
        $collation = $this->charset($type->charset, $collation ?? $this->collation);
        $members = array_map(static fn ($member): string => Encoding::convert($member->value, Charset::known('utf8mb4'), $collation->charset), $type->members);
        $lengths = array_map(static fn (string $member): int => $collation->charset->length($member), $members);
        $length = $type->kind === EnumerationKind::Enum ? max([0, ...$lengths]) : array_sum($lengths) + max(0, count($members) - 1);

        return new Domain(Kind::String, $type->kind === EnumerationKind::Enum ? Field::Enum : Field::Set, $length, Domain::NOT_FIXED, false, $collation, true, $members);
    }

    /**
     * Resolves BOOL, SERIAL, JSON, BIT and VECTOR.
     */
    public function elementary(Elementary $type): Domain
    {
        return match ($type->kind) {
            ElementaryKind::Boolean => Domain::integer(Field::Tiny, 1),
            ElementaryKind::Serial => Domain::integer(Field::LongLong, 20, true),
            ElementaryKind::Json => new Domain(Kind::Json, Field::Json, 4294967295, Domain::NOT_FIXED, false, Collation::known('utf8mb4_bin')),
            ElementaryKind::Bit => new Domain(Kind::Bit, Field::Bit, $type->length === null ? 1 : (int) $type->length, 0, true),
            ElementaryKind::Vector => new Domain(Kind::String, Field::Vector, ($type->length === null ? 2048 : (int) $type->length) * 4, Domain::NOT_FIXED, false, Collation::binary()),
        };
    }
}
