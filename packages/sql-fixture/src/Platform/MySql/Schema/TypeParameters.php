<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Schema;

use SqlFixture\Schema\TypeShape;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Type\Binary;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Decimal;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Enumeration;
use SqlSemantics\Platform\MySql\Statement\Type\Floating;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;
use SqlSemantics\Platform\MySql\Statement\Type\TypeName;

/**
 * Reads the name, size, sign and permitted values of a column's data type.
 *
 * Synonyms are named as the server names them: INTEGER is INT, NUMERIC, DEC
 * and FIXED are DECIMAL, LONG VARCHAR is MEDIUMTEXT, and FLOAT(p) is FLOAT
 * up to a precision of 24 and DOUBLE above it.
 *
 * @visibility root
 */
final class TypeParameters
{
    /**
     * Returns the type name and the sizes written in its parentheses.
     */
    public function shape(TypeName $type): TypeShape
    {
        return match (true) {
            $type instanceof Integral => TypeShape::fromNumbers($type->kind->value, $this->numbers($type->width)),
            $type instanceof Decimal => TypeShape::fromNumbers('DECIMAL', $this->numbers($type->precision, $type->scale), decimal: true),
            $type instanceof Floating && $type->kind === FloatingKind::Float && $type->precision !== null && $type->scale === null => new TypeShape((int) $type->precision > 24 ? 'DOUBLE' : 'FLOAT'),
            $type instanceof Floating => TypeShape::fromNumbers($type->kind->value, $this->numbers($type->precision, $type->scale)),
            $type instanceof Character => TypeShape::fromNumbers($this->characterName($type->kind), $this->numbers($type->length)),
            $type instanceof Binary => TypeShape::fromNumbers($type->kind === BinaryKind::LongVarBinary ? 'MEDIUMBLOB' : $type->kind->value, $this->numbers($type->length)),
            $type instanceof Temporal => TypeShape::fromNumbers($type->kind->value, $this->numbers($type->precision)),
            $type instanceof Elementary && $type->kind === ElementaryKind::Serial => new TypeShape('BIGINT', autoIncrement: true),
            $type instanceof Elementary && $type->kind === ElementaryKind::Boolean => new TypeShape('BOOLEAN'),
            $type instanceof Elementary => TypeShape::fromNumbers($type->kind->value, $this->numbers($type->length)),
            default => new TypeShape(strtoupper($type->name())),
        };
    }

    /**
     * Tells whether the type holds no negative number: UNSIGNED or ZEROFILL is written, or the type is SERIAL.
     */
    public function unsigned(TypeName $type): bool
    {
        return match (true) {
            $type instanceof Integral => $type->unsigned(),
            $type instanceof Decimal, $type instanceof Floating => in_array(NumericModifier::Unsigned, $type->modifiers, true) || in_array(NumericModifier::Zerofill, $type->modifiers, true),
            $type instanceof Elementary => $type->kind === ElementaryKind::Serial,
            default => false,
        };
    }

    /**
     * Tells whether the type holds numbers, so that a hexadecimal or bit literal stored in it is a number.
     */
    public function numeric(TypeName $type): bool
    {
        return $type instanceof Integral || $type instanceof Decimal || $type instanceof Floating
            || ($type instanceof Elementary && in_array($type->kind, [ElementaryKind::Bit, ElementaryKind::Boolean, ElementaryKind::Serial], true));
    }

    /**
     * Returns the permitted values of an ENUM or SET type without the trailing spaces the server removes, and null for every other type.
     *
     * @return list<string>|null
     */
    public function members(TypeName $type): ?array
    {
        if (!$type instanceof Enumeration) {
            return null;
        }

        return array_map(fn (Text $member): string => rtrim($this->member($member), ' '), $type->members);
    }

    /**
     * Returns the string a member denotes; a hexadecimal or bit member denotes the bytes of its digits.
     */
    public function member(Text $member): string
    {
        return match ($member->radix) {
            Radix::Hexadecimal => (string) hex2bin(str_pad($member->value, strlen($member->value) + strlen($member->value) % 2, '0', STR_PAD_LEFT)),
            Radix::Bit => implode('', array_map(static fn (string $byte): string => chr((int) bindec($byte)), str_split(str_pad($member->value, (int) ceil(strlen($member->value) / 8) * 8, '0', STR_PAD_LEFT), 8))),
            null => $member->value,
        };
    }

    /**
     * Names a character type as the server names it.
     */
    public function characterName(CharacterKind $kind): string
    {
        return match ($kind) {
            CharacterKind::CharVarying => 'VARCHAR',
            CharacterKind::Long, CharacterKind::LongVarChar, CharacterKind::LongCharVarying => 'MEDIUMTEXT',
            CharacterKind::Char, CharacterKind::VarChar, CharacterKind::TinyText, CharacterKind::Text, CharacterKind::MediumText, CharacterKind::LongText => $kind->value,
        };
    }

    /**
     * Returns the sizes that are written, in order.
     *
     * @return list<int>
     */
    public function numbers(?string ...$written): array
    {
        $numbers = [];
        foreach ($written as $number) {
            if ($number !== null) {
                $numbers[] = (int) $number;
            }
        }

        return $numbers;
    }
}
