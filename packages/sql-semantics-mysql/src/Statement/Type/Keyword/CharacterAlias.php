<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type\Keyword;

use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;

/**
 * The written synonyms of a character type, separate from its execution kind.
 *
 * @visibility public
 * @example Reading the retained keyword
 *     \SqlSemantics\Platform\MySql\Statement\Type\Keyword\CharacterAlias::Character->value // => 'CHARACTER'
 */
enum CharacterAlias: string
{
    case Character = 'CHARACTER';
    case VarCharacter = 'VARCHARACTER';
    case CharacterVarying = 'CHARACTER VARYING';
    case LongVarCharacter = 'LONG VARCHARACTER';
    case LongCharacterVarying = 'LONG CHARACTER VARYING';
    case NationalChar = 'NATIONAL CHAR';
    case NationalCharacter = 'NATIONAL CHARACTER';
    case NationalVarChar = 'NATIONAL VARCHAR';
    case NationalVarCharacter = 'NATIONAL VARCHARACTER';
    case NcharVarChar = 'NCHAR VARCHAR';
    case NcharVarCharacter = 'NCHAR VARCHARACTER';
    case NationalCharVarying = 'NATIONAL CHAR VARYING';
    case NationalCharacterVarying = 'NATIONAL CHARACTER VARYING';
    case NcharVarying = 'NCHAR VARYING';

    /**
     * Answers the canonical type requested by this keyword alternative.
     */
    public function kind(): CharacterKind
    {
        return match ($this) {
            self::Character, self::NationalChar, self::NationalCharacter => CharacterKind::Char,
            self::VarCharacter, self::NationalVarChar, self::NationalVarCharacter, self::NcharVarChar, self::NcharVarCharacter, self::NationalCharVarying, self::NationalCharacterVarying, self::NcharVarying => CharacterKind::VarChar,
            self::CharacterVarying => CharacterKind::CharVarying,
            self::LongVarCharacter => CharacterKind::LongVarChar,
            self::LongCharacterVarying => CharacterKind::LongCharVarying,
        };
    }

    /**
     * Tells whether the alternative requests the national character set.
     */
    public function national(): bool
    {
        return match ($this) {
            self::Character, self::VarCharacter, self::CharacterVarying, self::LongVarCharacter, self::LongCharacterVarying => false,
            self::NationalChar, self::NationalCharacter, self::NationalVarChar, self::NationalVarCharacter,
            self::NcharVarChar, self::NcharVarCharacter, self::NationalCharVarying, self::NationalCharacterVarying, self::NcharVarying => true,
        };
    }
}
