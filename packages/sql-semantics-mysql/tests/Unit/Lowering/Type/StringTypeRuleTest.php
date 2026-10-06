<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Type\StringTypeRule;
use SqlSemantics\Platform\MySql\Lowering\Type\TypePartRule;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Type\Binary;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Enumeration;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\EnumerationKind;

#[CoversClass(StringTypeRule::class)]
#[Medium]
final class StringTypeRuleTest extends TestCase
{
    public function testTypeLowersCharacterBinaryAndEnumerationTypes(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $rule = new StringTypeRule($lowering, new TypePartRule($lowering));
        $length = new Node('field_length', 3, [new Token(0, '(', '(', 0), new Token(0, 'NUM', '10', 0), new Token(0, ')', ')', 0)]);
        $none = new Node('opt_charset_with_opt_binary', 0, []);
        $string = static fn (string $text): Node => new Node('text_string', 0, [new Node('TEXT_STRING_literal', 0, [new Token(0, 'TEXT_STRING', $text, 0)])]);
        $members = new Node('string_list', 1, [new Node('string_list', 0, [$string("'a'")]), new Token(0, ',', ',', 0), $string("'b'")]);
        $char = $rule->type(new Form(new Node('type', 7, [new Token(0, 'CHAR_SYM', 'CHAR', 0), $length, new Node('opt_charset_with_opt_binary', 3, [new Token(0, 'BYTE_SYM', 'BYTE', 0)])]), 'type: CHAR_SYM field_length opt_charset_with_opt_binary'));
        $text = $rule->type(new Form(new Node('type', 29, [new Token(0, 'TEXT_SYM', 'TEXT', 0), new Node('opt_field_length', 0, []), $none]), 'type: TEXT_SYM opt_field_length opt_charset_with_opt_binary'));
        $blob = $rule->type(new Form(new Node('type', 22, [new Token(0, 'BLOB_SYM', 'BLOB', 0), new Node('opt_field_length', 1, [$length])]), 'type: BLOB_SYM opt_field_length'));
        $set = $rule->type(new Form(new Node('type', 33, [new Token(0, 'SET_SYM', 'SET', 0), new Token(0, '(', '(', 0), $members, new Token(0, ')', ')', 0), $none]), 'type: SET_SYM ( string_list ) opt_charset_with_opt_binary'));

        self::assertInstanceOf(Character::class, $char);
        self::assertSame(CharacterKind::Char, $char->kind);
        self::assertSame('10', $char->length);
        self::assertSame(CharsetForm::Byte, $char->charset?->form);
        self::assertInstanceOf(Character::class, $text);
        self::assertSame(CharacterKind::Text, $text->kind);
        self::assertInstanceOf(Binary::class, $blob);
        self::assertSame(BinaryKind::Blob, $blob->kind);
        self::assertSame('10', $blob->length);
        self::assertInstanceOf(Enumeration::class, $set);
        self::assertSame(EnumerationKind::Set, $set->kind);
        self::assertCount(2, $set->members);
        self::assertNull($rule->type(new Form(new Node('type', 17, [new Token(0, 'DATE_SYM', 'DATE', 0)]), 'type: DATE_SYM')));
    }

    public function testVaryingKeepsTheSpellingOfVarchar(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $rule = new StringTypeRule($lowering, new TypePartRule($lowering));
        $length = new Node('field_length', 3, [new Token(0, '(', '(', 0), new Token(0, 'NUM', '20', 0), new Token(0, ')', ')', 0)]);
        $none = new Node('opt_charset_with_opt_binary', 0, []);
        $varchar = $rule->varying(new Form(new Node('type', 13, [new Node('varchar', 1, [new Token(0, 'VARCHAR_SYM', 'VARCHAR', 0)]), $length, $none]), 'type: varchar field_length opt_charset_with_opt_binary'));
        $varying = $rule->varying(new Form(new Node('type', 13, [new Node('varchar', 0, [new Node('char', 0, [new Token(0, 'CHAR_SYM', 'CHAR', 0)]), new Token(0, 'VARYING', 'VARYING', 0)]), $length, $none]), 'type: varchar field_length opt_charset_with_opt_binary'));
        $long = $rule->varying(new Form(new Node('type', 27, [new Token(0, 'LONG_SYM', 'LONG', 0), new Node('varchar', 1, [new Token(0, 'VARCHAR_SYM', 'VARCHAR', 0)]), $none]), 'type: LONG_SYM varchar opt_charset_with_opt_binary'));

        self::assertSame(CharacterKind::VarChar, $varchar?->kind);
        self::assertSame('20', $varchar->length);
        self::assertSame(CharacterKind::CharVarying, $varying?->kind);
        self::assertSame(CharacterKind::LongVarChar, $long?->kind);
        self::assertNull($rule->varying(new Form(new Node('type', 17, []), 'type: DATE_SYM')));
    }

    public function testNationalLowersNationalCharacterTypes(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $rule = new StringTypeRule($lowering, new TypePartRule($lowering));
        $length = new Node('field_length', 3, [new Token(0, '(', '(', 0), new Token(0, 'NUM', '5', 0), new Token(0, ')', ')', 0)]);
        $nchar = $rule->national(new Form(new Node('type', 9, [new Node('nchar', 1, [new Token(0, 'NATIONAL_SYM', 'NATIONAL', 0), new Token(0, 'CHAR_SYM', 'CHAR', 0)]), $length, new Node('opt_bin_mod', 1, [new Token(0, 'BINARY_SYM', 'BINARY', 0)])]), 'type: nchar field_length opt_bin_mod'));
        $nvarchar = $rule->national(new Form(new Node('type', 14, [new Node('nvarchar', 0, [new Token(0, 'NATIONAL_SYM', 'NATIONAL', 0), new Token(0, 'VARCHAR_SYM', 'VARCHAR', 0)]), $length, new Node('opt_bin_mod', 0, [])]), 'type: nvarchar field_length opt_bin_mod'));

        self::assertSame(CharacterKind::Char, $nchar?->kind);
        self::assertTrue($nchar->national);
        self::assertSame(CharsetForm::Binary, $nchar->charset?->form);
        self::assertSame(CharacterKind::VarChar, $nvarchar?->kind);
        self::assertNull($nvarchar->charset);
        self::assertNull($rule->national(new Form(new Node('type', 17, []), 'type: DATE_SYM')));
    }

    public function testKeywordReportsAProductionWithoutARule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $rule = new StringTypeRule($lowering, new TypePartRule($lowering));
        $rule->keyword(new Node('nchar', 0, [new Token(0, 'NCHAR_SYM', 'NCHAR', 0)]));

        $this->expectExceptionMessage('No semantic rule is implemented for: varchar: VARCHAR_SYM');

        $rule->keyword(new Node('varchar', 1, [new Token(0, 'VARCHAR_SYM', 'VARCHAR', 0)]));
    }
}
