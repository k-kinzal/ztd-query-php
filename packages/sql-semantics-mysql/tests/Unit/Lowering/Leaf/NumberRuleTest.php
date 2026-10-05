<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Leaf;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Leaf\NumberRule;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;

#[CoversClass(NumberRule::class)]
#[Medium]
final class NumberRuleTest extends TestCase
{
    public function testNumeralKeepsTheDigitsOfEveryNumberToken(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new NumberRule(new Lowering($platform->productions($profile), new Leaves(), $profile));
        $decimal = $rule->numeral(new Node('ulong_num', 0, [new Token(0, 'NUM', '42', 0)]));
        $hexadecimal = $rule->numeral(new Node('ulong_num', 1, [new Token(0, 'HEX_NUM', '0x1F', 0)]));
        $fraction = $rule->numeral(new Node('real_ulong_num', 4, [new Node('dec_num_error', 0, [new Node('dec_num', 0, [new Token(0, 'DECIMAL_NUM', '1.5', 0)])])]));

        self::assertSame('42', $decimal->text);
        self::assertFalse($decimal->hexadecimal);
        self::assertSame('1F', $hexadecimal->text);
        self::assertTrue($hexadecimal->hexadecimal);
        self::assertSame('1.5', $fraction->text);
    }

    public function testNumeralReportsAProductionWithoutARule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new NumberRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: size_number: IDENT_sys');

        $rule->numeral(new Node('size_number', 1, [new Node('IDENT_sys', 0, [new Token(0, 'IDENT', '4M', 0)])]));
    }

    public function testTokenKeepsTheTextOfABareNumberAndRecordsIt(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $leaves = new Leaves();
        $number = (new NumberRule(new Lowering($platform->productions($profile), $leaves, $profile)))->token(new Token(0, 'NUM', '007', 0));

        self::assertSame('007', $number->text);
        self::assertFalse($number->hexadecimal);
        self::assertSame([$number], $leaves->all());
    }

    public function testTokenRefusesATokenThatIsNotANumber(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);

        $this->expectExceptionMessage('A bare number is a NUM token.');

        (new NumberRule(new Lowering($platform->productions($profile), new Leaves(), $profile)))->token(new Token(0, 'IDENT', 'x', 0));
    }

    public function testSizeLowersANumberOrAWordWithAUnit(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new NumberRule(new Lowering($platform->productions($profile), new Leaves(), $profile));
        $number = $rule->size(new Node('size_number', 0, [new Node('real_ulonglong_num', 0, [new Token(0, 'NUM', '1024', 0)])]));
        $word = $rule->size(new Node('size_number', 1, [new Node('IDENT_sys', 0, [new Token(0, 'IDENT', '4M', 0)])]));
        $option = $rule->size(new Node('option_autoextend_size', 0, [new Token(0, 'AUTOEXTEND_SIZE_SYM', 'AUTOEXTEND_SIZE', 0), new Node('opt_equal', 0, []), new Node('size_number', 0, [new Node('real_ulonglong_num', 0, [new Token(0, 'NUM', '8', 0)])])]));

        self::assertSame('1024', $number->number?->text);
        self::assertNull($number->word);
        self::assertNull($word->number);
        self::assertSame('4M', $word->word?->value);
        self::assertSame('8', $option->number?->text);
    }

    public function testTernaryLowersANumberOrDefault(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new NumberRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        self::assertSame('1', $rule->ternary(new Node('ternary_option', 0, [new Node('ulong_num', 0, [new Token(0, 'NUM', '1', 0)])]))?->text);
        self::assertNull($rule->ternary(new Node('ternary_option', 1, [new Token(0, 'DEFAULT_SYM', 'DEFAULT', 0)])));
    }
}
