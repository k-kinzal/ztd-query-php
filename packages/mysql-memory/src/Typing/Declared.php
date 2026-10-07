<?php

declare(strict_types=1);

namespace MySqlMemory\Typing;

use MySqlMemory\Result\FieldType;
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
    public function __construct(public readonly Collation $collation = Collation::Utf8mb40900AiCi)
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
            default => new Domain(Kind::String, FieldType::Blob, 4294967295, Domain::NOT_FIXED, false, Collation::Binary),
        };
    }

    /**
     * Resolves an integer type.
     */
    public function integral(Integral $type): Domain
    {
        $unsigned = in_array(NumericModifier::Unsigned, $type->modifiers, true) || in_array(NumericModifier::Zerofill, $type->modifiers, true);
        [$field, $length] = match ($type->kind) {
            IntegralKind::TinyInt => [FieldType::Tiny, $unsigned ? 3 : 4],
            IntegralKind::SmallInt => [FieldType::Short, $unsigned ? 5 : 6],
            IntegralKind::MediumInt => [FieldType::Int24, $unsigned ? 8 : 9],
            IntegralKind::Int => [FieldType::Long, $unsigned ? 10 : 11],
            IntegralKind::BigInt => [FieldType::LongLong, 20],
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

        return new Domain(Kind::Double, $single ? FieldType::Float : FieldType::Double, $length, $decimals, $unsigned);
    }

    /**
     * Resolves a character string type.
     */
    public function character(Character $type, ?Collation $collation): Domain
    {
        $collation = $this->charset($type->charset, $type->national ? Collation::Utf8mb3GeneralCi : ($collation ?? $this->collation));
        [$field, $length] = match ($type->kind) {
            CharacterKind::Char => [FieldType::String, $type->length === null ? 1 : (int) $type->length],
            CharacterKind::VarChar, CharacterKind::CharVarying => [FieldType::VarString, (int) $type->length],
            CharacterKind::TinyText => [FieldType::Blob, 255],
            CharacterKind::Text => [FieldType::Blob, $type->length === null ? 65535 : $this->textLength((int) $type->length, $collation)],
            CharacterKind::MediumText, CharacterKind::Long, CharacterKind::LongVarChar, CharacterKind::LongCharVarying => [FieldType::Blob, 16777215],
            CharacterKind::LongText => [FieldType::Blob, 4294967295],
        };

        return Domain::string(in_array($type->kind, [CharacterKind::TinyText, CharacterKind::Text, CharacterKind::MediumText, CharacterKind::LongText, CharacterKind::Long, CharacterKind::LongVarChar, CharacterKind::LongCharVarying], true) ? intdiv($length, $collation->charset()->maxLength()) : $length, $collation, $field);
    }

    /**
     * Answers the byte length of the smallest TEXT type that holds a number of characters.
     */
    public function textLength(int $characters, Collation $collation): int
    {
        $bytes = $characters * $collation->charset()->maxLength();

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
            CharsetForm::Ascii => Collation::AsciiGeneralCi,
            CharsetForm::Unicode => Collation::Utf8mb40900AiCi,
            CharsetForm::Byte => Collation::Binary,
            CharsetForm::Binary => Collation::named($collation->charset()->value . '_bin') ?? Collation::Utf8mb4Bin,
            CharsetForm::Named, CharsetForm::CharacterSet => $attribute->charset === null ? $collation : (Charset::named($attribute->charset->value)?->defaultCollation() ?? $collation),
        };
    }

    /**
     * Resolves a binary string type.
     */
    public function binary(Binary $type): Domain
    {
        [$field, $length] = match ($type->kind) {
            BinaryKind::Binary => [FieldType::String, $type->length === null ? 1 : (int) $type->length],
            BinaryKind::VarBinary => [FieldType::VarString, (int) $type->length],
            BinaryKind::TinyBlob => [FieldType::Blob, 255],
            BinaryKind::Blob => [FieldType::Blob, $type->length === null ? 65535 : $this->textLength((int) $type->length, Collation::Binary)],
            BinaryKind::MediumBlob, BinaryKind::LongVarBinary => [FieldType::Blob, 16777215],
            BinaryKind::LongBlob => [FieldType::Blob, 4294967295],
        };

        return Domain::string($length, Collation::Binary, $field);
    }

    /**
     * Resolves a date, time, datetime, timestamp or year type.
     */
    public function temporal(Temporal $type): Domain
    {
        $decimals = $type->precision === null ? 0 : (int) $type->precision;
        $fraction = $decimals > 0 ? $decimals + 1 : 0;

        return match ($type->kind) {
            TemporalKind::Date => new Domain(Kind::Date, FieldType::Date, 10),
            TemporalKind::Time => new Domain(Kind::Time, FieldType::Time, 10 + $fraction, $decimals),
            TemporalKind::DateTime => new Domain(Kind::DateTime, FieldType::DateTime, 19 + $fraction, $decimals),
            TemporalKind::Timestamp => new Domain(Kind::DateTime, FieldType::Timestamp, 19 + $fraction, $decimals),
            TemporalKind::Year => new Domain(Kind::Year, FieldType::Year, 4, 0, true),
        };
    }

    /**
     * Resolves ENUM and SET.
     */
    public function enumeration(Enumeration $type, ?Collation $collation): Domain
    {
        $collation = $this->charset($type->charset, $collation ?? $this->collation);
        $members = array_map(static fn ($member): string => $member->value(), $type->members);
        $lengths = array_map(static fn (string $member): int => $collation->charset()->length($member), $members);
        $length = $type->kind === EnumerationKind::Enum ? max([0, ...$lengths]) : array_sum($lengths) + max(0, count($members) - 1);

        return new Domain(Kind::String, $type->kind === EnumerationKind::Enum ? FieldType::Enum : FieldType::Set, $length, Domain::NOT_FIXED, false, $collation, true, $members);
    }

    /**
     * Resolves BOOL, SERIAL, JSON, BIT and VECTOR.
     */
    public function elementary(Elementary $type): Domain
    {
        return match ($type->kind) {
            ElementaryKind::Boolean => Domain::integer(FieldType::Tiny, 1),
            ElementaryKind::Serial => Domain::integer(FieldType::LongLong, 20, true),
            ElementaryKind::Json => new Domain(Kind::Json, FieldType::Json, 4294967295, Domain::NOT_FIXED, false, Collation::Utf8mb4Bin),
            ElementaryKind::Bit => new Domain(Kind::Bit, FieldType::Bit, $type->length === null ? 1 : (int) $type->length, 0, true),
            ElementaryKind::Vector => new Domain(Kind::String, FieldType::Vector, ($type->length === null ? 2048 : (int) $type->length) * 4, Domain::NOT_FIXED, false, Collation::Binary),
        };
    }
}
