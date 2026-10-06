<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Type\Designation;

/**
 * The keyword spellings of the character types.
 *
 * Every spelling denotes `character`, or `character varying` when VARYING
 * follows or the spelling is VARCHAR; the national spellings are not a
 * different type in PostgreSQL.
 * Source: https://www.postgresql.org/docs/17/datatype-character.html.
 *
 * @visibility public
 * @example Spelling the national form
 *     \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\CharacterKeyword::NationalCharacter->value // => 'NATIONAL CHARACTER'
 */
enum CharacterKeyword: string
{
    case Character = 'CHARACTER';
    case Char = 'CHAR';
    case Varchar = 'VARCHAR';
    case NationalCharacter = 'NATIONAL CHARACTER';
    case NationalChar = 'NATIONAL CHAR';
    case Nchar = 'NCHAR';
}
