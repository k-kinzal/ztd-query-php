<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Type\TypePartRule;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryMark;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;

#[CoversClass(TypePartRule::class)]
#[Medium]
final class TypePartRuleTest extends TestCase
{
    public function testLengthLowersAnOptionalLength(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new TypePartRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        self::assertNull($rule->length(new Node('opt_field_length', 0, [])));
        self::assertSame('255', $rule->length(new Node('opt_field_length', 1, [new Node('field_length', 3, [new Token(0, '(', '(', 0), new Token(0, 'NUM', '255', 0), new Token(0, ')', ')', 0)])])));
    }

    public function testNumbersLowersPrecisionAndScale(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new TypePartRule(new Lowering($platform->productions($profile), new Leaves(), $profile));
        $precision = new Node('precision', 0, [new Token(0, '(', '(', 0), new Token(0, 'NUM', '10', 0), new Token(0, ',', ',', 0), new Token(0, 'NUM', '2', 0), new Token(0, ')', ')', 0)]);

        self::assertSame([null, null], $rule->numbers(new Node('float_options', 0, [])));
        self::assertSame(['10', '2'], $rule->numbers(new Node('float_options', 2, [$precision])));
        self::assertSame(['10', '2'], $rule->numbers(new Node('opt_precision', 1, [$precision])));
        self::assertSame(['5', null], $rule->numbers(new Node('standard_float_options', 1, [new Node('field_length', 3, [new Token(0, '(', '(', 0), new Token(0, 'NUM', '5', 0), new Token(0, ')', ')', 0)])])));
    }

    public function testModifiersLowersTheNumericAttributesInOrder(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new TypePartRule(new Lowering($platform->productions($profile), new Leaves(), $profile));
        $list = new Node('field_opt_list', 0, [new Node('field_opt_list', 1, [new Node('field_option', 2, [new Token(0, 'ZEROFILL_SYM', 'ZEROFILL', 0)])]), new Node('field_option', 0, [new Token(0, 'SIGNED_SYM', 'SIGNED', 0)])]);

        self::assertSame([], $rule->modifiers(new Node('field_options', 0, [])));
        self::assertSame([NumericModifier::Zerofill, NumericModifier::Signed], $rule->modifiers(new Node('field_options', 1, [$list])));
    }

    public function testBinaryTellsWhetherTheBinaryModifierIsWritten(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new TypePartRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        self::assertFalse($rule->binary(new Node('opt_bin_mod', 0, [])));
        self::assertTrue($rule->binary(new Node('opt_bin_mod', 1, [new Token(0, 'BINARY_SYM', 'BINARY', 0)])));
    }

    public function testNationalAttributeLowersTheBinaryModifierOfANationalType(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new TypePartRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        self::assertNull($rule->nationalAttribute(new Node('opt_bin_mod', 0, [])));
        self::assertSame(CharsetForm::Binary, $rule->nationalAttribute(new Node('opt_bin_mod', 1, [new Token(0, 'BINARY_SYM', 'BINARY', 0)]))?->form);
    }

    public function testCharsetLowersEveryCharacterSetAttributeForm(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new TypePartRule(new Lowering($platform->productions($profile), new Leaves(), $profile));
        $name = new Node('charset_name', 0, [new Node('ident_or_text', 0, [new Node('ident', 0, [new Node('IDENT_sys', 0, [new Token(0, 'IDENT', 'utf8mb4', 0)])])])]);
        $keyword = new Node('character_set', 1, [new Token(0, 'CHARSET', 'CHARSET', 0)]);
        $binary = new Token(0, 'BINARY_SYM', 'BINARY', 0);
        $named = $rule->charset(new Node('opt_charset_with_opt_binary', 4, [$keyword, $name, new Node('opt_bin_mod', 1, [$binary])]));
        $leading = $rule->charset(new Node('opt_charset_with_opt_binary', 6, [$binary, $keyword, $name]));

        self::assertNull($rule->charset(new Node('opt_charset_with_opt_binary', 0, [])));
        self::assertSame(CharsetForm::Byte, $rule->charset(new Node('opt_charset_with_opt_binary', 3, [new Token(0, 'BYTE_SYM', 'BYTE', 0)]))?->form);
        self::assertSame(CharsetForm::Binary, $rule->charset(new Node('opt_charset_with_opt_binary', 5, [$binary]))?->form);
        self::assertSame(CharsetForm::Ascii, $rule->charset(new Node('opt_charset_with_opt_binary', 1, [new Node('ascii', 0, [new Token(0, 'ASCII_SYM', 'ASCII', 0)])]))?->form);
        self::assertSame('utf8mb4', $named?->charset?->value);
        self::assertSame(BinaryMark::Trailing, $named->mark);
        self::assertSame(BinaryMark::Leading, $leading?->mark);
    }

    public function testShorthandLowersAsciiAndUnicodeWithTheirBinaryMark(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new TypePartRule(new Lowering($platform->productions($profile), new Leaves(), $profile));
        $ascii = $rule->shorthand(new Node('ascii', 1, [new Token(0, 'BINARY_SYM', 'BINARY', 0), new Token(0, 'ASCII_SYM', 'ASCII', 0)]));
        $unicode = $rule->shorthand(new Node('unicode', 1, [new Token(0, 'UNICODE_SYM', 'UNICODE', 0), new Token(0, 'BINARY_SYM', 'BINARY', 0)]));

        self::assertSame(CharsetForm::Ascii, $ascii->form);
        self::assertSame(BinaryMark::Leading, $ascii->mark);
        self::assertSame(CharsetForm::Unicode, $unicode->form);
        self::assertSame(BinaryMark::Trailing, $unicode->mark);
    }

    public function testNamedTellsHowACharacterSetIsIntroduced(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new TypePartRule(new Lowering($platform->productions($profile), new Leaves(), $profile));
        $parser = $platform->parser($profile);

        self::assertSame(CharsetForm::CharacterSet, $rule->named($parser->parse('SELECT CAST(a AS CHAR CHARACTER SET latin1)')->find('character_set')[0]));
        self::assertSame(CharsetForm::Named, $rule->named($parser->parse('SELECT CAST(a AS CHAR CHARSET latin1)')->find('character_set')[0]));
    }
}
