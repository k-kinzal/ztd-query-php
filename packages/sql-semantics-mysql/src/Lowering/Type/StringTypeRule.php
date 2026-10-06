<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Type;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Type\Binary;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Enumeration;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\EnumerationKind;
use SqlSemantics\Platform\MySql\Statement\Type\TypeName;

/**
 * Lowers the character string, binary string and enumeration type productions.
 *
 * Rule: MYSQL-STRING-TYPE-001. Scope: the string alternatives of type, and
 * char, nchar, varchar, nvarchar. Each production names its kind and the
 * positions of its length and character set attribute. The spellings of the
 * national types are one kind each; `CHAR VARYING` and `LONG CHAR VARYING`
 * are kinds of their own. Constructs: Character, Binary, Enumeration.
 * Terminates: every child is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-type-syntax.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class StringTypeRule
{
    /**
     * The character type productions: kind, position of the length, position of the character set attribute.
     */
    private const CHARACTERS = [
        'type: char field_length opt_binary' => [CharacterKind::Char, 1, 2], 'type: char opt_binary' => [CharacterKind::Char, null, 1],
        'type: CHAR_SYM field_length opt_charset_with_opt_binary' => [CharacterKind::Char, 1, 2],
        'type: CHAR_SYM opt_charset_with_opt_binary' => [CharacterKind::Char, null, 1],
        'type: TINYTEXT opt_binary' => [CharacterKind::TinyText, null, 1], 'type: TINYTEXT_SYN opt_charset_with_opt_binary' => [CharacterKind::TinyText, null, 1],
        'type: TEXT_SYM opt_field_length opt_binary' => [CharacterKind::Text, 1, 2],
        'type: TEXT_SYM opt_field_length opt_charset_with_opt_binary' => [CharacterKind::Text, 1, 2],
        'type: MEDIUMTEXT opt_binary' => [CharacterKind::MediumText, null, 1],
        'type: MEDIUMTEXT_SYM opt_charset_with_opt_binary' => [CharacterKind::MediumText, null, 1],
        'type: LONGTEXT opt_binary' => [CharacterKind::LongText, null, 1], 'type: LONGTEXT_SYM opt_charset_with_opt_binary' => [CharacterKind::LongText, null, 1],
        'type: LONG_SYM opt_binary' => [CharacterKind::Long, null, 1], 'type: LONG_SYM opt_charset_with_opt_binary' => [CharacterKind::Long, null, 1],
    ];

    /**
     * The VARCHAR productions written through the rule varchar: whether LONG precedes, position of the length, position of the attribute.
     */
    private const VARYING = [
        'type: varchar field_length opt_binary' => [false, 1, 2], 'type: varchar field_length opt_charset_with_opt_binary' => [false, 1, 2],
        'type: LONG_SYM varchar opt_binary' => [true, null, 2], 'type: LONG_SYM varchar opt_charset_with_opt_binary' => [true, null, 2],
    ];

    /**
     * The spellings of the rule varchar, by whether they are written `CHAR VARYING`.
     */
    private const VARCHAR = ['varchar: char VARYING' => true, 'varchar: VARCHAR' => false, 'varchar: CHAR_SYM VARYING' => true, 'varchar: VARCHAR_SYM' => false];

    /**
     * The national type productions: kind, position of the length, position of the BINARY attribute.
     */
    private const NATIONALS = [
        'type: nchar field_length opt_bin_mod' => [CharacterKind::Char, 1, 2], 'type: nchar opt_bin_mod' => [CharacterKind::Char, null, 1],
        'type: nvarchar field_length opt_bin_mod' => [CharacterKind::VarChar, 1, 2],
    ];

    /**
     * The spellings of the CHAR, NCHAR and NVARCHAR keywords, which are one kind each.
     */
    private const KEYWORDS = [
        'char: CHAR_SYM' => true, 'nchar: NCHAR_SYM' => true, 'nchar: NATIONAL_SYM CHAR_SYM' => true, 'nvarchar: NATIONAL_SYM VARCHAR' => true,
        'nvarchar: NVARCHAR_SYM' => true, 'nvarchar: NCHAR_SYM VARCHAR' => true, 'nvarchar: NATIONAL_SYM CHAR_SYM VARYING' => true,
        'nvarchar: NCHAR_SYM VARYING' => true, 'nvarchar: NATIONAL_SYM VARCHAR_SYM' => true, 'nvarchar: NCHAR_SYM VARCHAR_SYM' => true,
    ];

    /**
     * The binary type productions: kind and position of the length.
     */
    private const BINARIES = [
        'type: BINARY field_length' => [BinaryKind::Binary, 1], 'type: BINARY' => [BinaryKind::Binary, null],
        'type: BINARY_SYM field_length' => [BinaryKind::Binary, 1], 'type: BINARY_SYM' => [BinaryKind::Binary, null],
        'type: VARBINARY field_length' => [BinaryKind::VarBinary, 1], 'type: VARBINARY_SYM field_length' => [BinaryKind::VarBinary, 1],
        'type: TINYBLOB' => [BinaryKind::TinyBlob, null], 'type: TINYBLOB_SYM' => [BinaryKind::TinyBlob, null],
        'type: BLOB_SYM opt_field_length' => [BinaryKind::Blob, 1], 'type: MEDIUMBLOB' => [BinaryKind::MediumBlob, null],
        'type: MEDIUMBLOB_SYM' => [BinaryKind::MediumBlob, null], 'type: LONGBLOB' => [BinaryKind::LongBlob, null],
        'type: LONGBLOB_SYM' => [BinaryKind::LongBlob, null], 'type: LONG_SYM VARBINARY' => [BinaryKind::LongVarBinary, null],
        'type: LONG_SYM VARBINARY_SYM' => [BinaryKind::LongVarBinary, null],
    ];

    /**
     * The ENUM and SET productions.
     */
    private const ENUMERATIONS = [
        'type: ENUM ( string_list ) opt_binary' => EnumerationKind::Enum, 'type: SET ( string_list ) opt_binary' => EnumerationKind::Set,
        'type: ENUM_SYM ( string_list ) opt_charset_with_opt_binary' => EnumerationKind::Enum,
        'type: SET_SYM ( string_list ) opt_charset_with_opt_binary' => EnumerationKind::Set,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     * @param TypePartRule $parts The rules of lengths and attributes
     */
    public function __construct(private readonly Lowering $lowering, private readonly TypePartRule $parts)
    {
    }

    /**
     * Lowers a string or enumeration type production, or answers null for another kind of type.
     *
     * @throws ImplementationGap When a part has no rule
     */
    public function type(Form $form): ?TypeName
    {
        if (isset(self::CHARACTERS[$form->signature])) {
            [$kind, $length, $charset] = self::CHARACTERS[$form->signature];
            if ($form->signature === 'type: char field_length opt_binary' || $form->signature === 'type: char opt_binary') {
                $this->keyword($form->node(0));
            }

            return new Character($kind, $length === null ? null : $this->parts->length($form->node($length)), false, $this->parts->charset($form->node($charset)));
        }
        if (isset(self::BINARIES[$form->signature])) {
            [$kind, $length] = self::BINARIES[$form->signature];

            return new Binary($kind, $length === null ? null : $this->parts->length($form->node($length)));
        }
        if (isset(self::ENUMERATIONS[$form->signature])) {
            return new Enumeration(self::ENUMERATIONS[$form->signature], $this->lowering->literals->texts($form->node(2)), $this->parts->charset($form->node(4)));
        }

        return $this->varying($form) ?? $this->national($form);
    }

    /**
     * Lowers a VARCHAR type written through the rule varchar, or answers null for another kind of type.
     *
     * @throws ImplementationGap When a part has no rule
     */
    public function varying(Form $form): ?Character
    {
        if (!isset(self::VARYING[$form->signature])) {
            return null;
        }
        [$long, $length, $charset] = self::VARYING[$form->signature];
        $keyword = $this->lowering->productions->form($form->node($long ? 1 : 0));
        $spelledVarying = self::VARCHAR[$keyword->signature] ?? throw ImplementationGap::production($keyword);
        if ($keyword->signature === 'varchar: char VARYING') {
            $this->keyword($keyword->node(0));
        }
        $kind = match (true) {
            $long && $spelledVarying => CharacterKind::LongCharVarying,
            $long => CharacterKind::LongVarChar,
            $spelledVarying => CharacterKind::CharVarying,
            default => CharacterKind::VarChar,
        };

        return new Character($kind, $length === null ? null : $this->parts->length($form->node($length)), false, $this->parts->charset($form->node($charset)));
    }

    /**
     * Lowers a national character type, or answers null for another kind of type.
     *
     * @throws ImplementationGap When a part has no rule
     */
    public function national(Form $form): ?Character
    {
        if (!isset(self::NATIONALS[$form->signature])) {
            return null;
        }
        [$kind, $length, $binary] = self::NATIONALS[$form->signature];
        $this->keyword($form->node(0));

        return new Character($kind, $length === null ? null : $this->parts->length($form->node($length)), true, $this->parts->nationalAttribute($form->node($binary)));
    }

    /**
     * Confirms that a node is one of the spellings of CHAR, NCHAR or NVARCHAR.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function keyword(Node $keyword): void
    {
        $form = $this->lowering->productions->form($keyword);
        if (!isset(self::KEYWORDS[$form->signature])) {
            throw ImplementationGap::production($form);
        }
    }
}
