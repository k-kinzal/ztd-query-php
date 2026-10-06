<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Leaf;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Leaf\LiteralRule;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Mode;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Literal\EscapeRule;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\SignedLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;

#[CoversClass(LiteralRule::class)]
#[Medium]
final class LiteralRuleTest extends TestCase
{
    public function testEscapesFollowsTheProfile(): void
    {
        $platform = new Platform();
        $plain = $platform->profile(null, null, ParameterStyle::Native);
        $verbatim = $platform->profile(null, Mode::fromString('NO_BACKSLASH_ESCAPES'), ParameterStyle::Native);

        self::assertSame(EscapeRule::Backslash, (new LiteralRule(new Lowering($platform->productions($plain), new Leaves(), $plain)))->escapes());
        self::assertSame(EscapeRule::Verbatim, (new LiteralRule(new Lowering($platform->productions($verbatim), new Leaves(), $verbatim)))->escapes());
    }

    public function testLiteralLowersEveryLiteralForm(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze("SELECT 1, 1.5, 1e3, 'a', NULL, TRUE, FALSE, 0x1F, b'10', DATE '2024-01-01', TIME '12:00:00.5', TIMESTAMP '2024-01-01 12:00:00'");

        self::assertInstanceOf(Select::class, $operation->statement);
        $item0 = $operation->statement->items[0];
        self::assertInstanceOf(SelectExpression::class, $item0);
        self::assertInstanceOf(NumberLiteral::class, $item0->expression);
        $item3 = $operation->statement->items[3];
        self::assertInstanceOf(SelectExpression::class, $item3);
        self::assertInstanceOf(StringLiteral::class, $item3->expression);
        $item7 = $operation->statement->items[7];
        self::assertInstanceOf(SelectExpression::class, $item7);
        self::assertInstanceOf(RadixLiteral::class, $item7->expression);
        $item10 = $operation->statement->items[10];
        self::assertInstanceOf(SelectExpression::class, $item10);
        self::assertInstanceOf(TemporalLiteral::class, $item10->expression);
        self::assertSame(TemporalForm::Time, $item10->expression->form);
        self::assertSame("SELECT 1, 1.5, 1e3, 'a', NULL, TRUE, FALSE, 0x1F, b'10', DATE '2024-01-01', TIME '12:00:00.5', TIMESTAMP '2024-01-01 12:00:00'", $operation->toString());
    }

    public function testConstantLowersIntroducedRadixLiteralsAndSignedNumbers(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new LiteralRule(new Lowering($platform->productions($profile), new Leaves(), $profile));
        $number = new Node('NUM_literal', 0, [new Node('int64_literal', 0, [new Token(0, 'NUM', '7', 0)])]);
        $negative = $rule->constant(new Form(new Node('signed_literal', 2, [new Token(0, '-', '-', 0), $number]), 'signed_literal: - NUM_literal'));
        $introduced = (new Semantics(Dialect::MySql))->analyze("SELECT _latin1 X'4142', _utf8mb4 0b1")->statement;

        self::assertInstanceOf(SignedLiteral::class, $negative);
        self::assertTrue($negative->negative);
        self::assertSame('7', $negative->number->text);
        self::assertInstanceOf(Select::class, $introduced);
        $item0 = $introduced->items[0];
        self::assertInstanceOf(SelectExpression::class, $item0);
        self::assertInstanceOf(RadixLiteral::class, $item0->expression);
        self::assertSame('latin1', $item0->expression->introducer?->value);
        self::assertSame(Radix::Hexadecimal, $item0->expression->radix);
        $item1 = $introduced->items[1];
        self::assertInstanceOf(SelectExpression::class, $item1);
        self::assertInstanceOf(RadixLiteral::class, $item1->expression);
        self::assertSame(Radix::Bit, $item1->expression->radix);
    }

    public function testNumberLowersAnUnsignedNumber(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new LiteralRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        self::assertSame('007', $rule->number(new Node('NUM_literal', 0, [new Node('int64_literal', 0, [new Token(0, 'NUM', '007', 0)])]))->text);
        self::assertSame('1.5', $rule->number(new Node('NUM_literal', 1, [new Token(0, 'DECIMAL_NUM', '1.5', 0)]))->text);
    }

    public function testStringLowersAdjacentSegmentsIntroducersAndTheNationalPrefix(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("SELECT 'a' 'b' \"c\", _utf8mb4 'x' 'y', N'n' 'm'");

        self::assertInstanceOf(Select::class, $operation->statement);
        $item0 = $operation->statement->items[0];
        self::assertInstanceOf(SelectExpression::class, $item0);
        self::assertInstanceOf(StringLiteral::class, $item0->expression);
        self::assertSame(['a', 'b', 'c'], $item0->expression->segments);
        $item1 = $operation->statement->items[1];
        self::assertInstanceOf(SelectExpression::class, $item1);
        self::assertInstanceOf(StringLiteral::class, $item1->expression);
        self::assertSame('utf8mb4', $item1->expression->introducer?->value);
        $item2 = $operation->statement->items[2];
        self::assertInstanceOf(SelectExpression::class, $item2);
        self::assertInstanceOf(StringLiteral::class, $item2->expression);
        self::assertTrue($item2->expression->national);
        self::assertSame("SELECT 'a' 'b' 'c', _utf8mb4 'x' 'y', N'n' 'm'", $operation->toString());
    }

    public function testIntroducerNamesTheCharacterSetInLowerCase(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rule = new LiteralRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        self::assertSame('utf8mb4', $rule->introducer(new Form(new Node('literal', 0, [new Token(0, 'UNDERSCORE_CHARSET', '_UTF8MB4', 0)]), 'literal: UNDERSCORE_CHARSET HEX_NUM'))->value);
    }

    public function testDecodeFollowsTheEscapeSettingOfTheProfile(): void
    {
        $platform = new Platform();
        $plain = $platform->profile(null, null, ParameterStyle::Native);
        $verbatim = $platform->profile(null, Mode::fromString('NO_BACKSLASH_ESCAPES'), ParameterStyle::Native);

        self::assertSame("a\nb", (new LiteralRule(new Lowering($platform->productions($plain), new Leaves(), $plain)))->decode("'a\\nb'"));
        self::assertSame('a\\nb', (new LiteralRule(new Lowering($platform->productions($verbatim), new Leaves(), $verbatim)))->decode("'a\\nb'"));
    }

    public function testBytesDecodesAStringRuleWithoutRecordingALeaf(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $leaves = new Leaves();
        $rule = new LiteralRule(new Lowering($platform->productions($profile), $leaves, $profile));

        self::assertSame("it's", $rule->bytes(new Node('TEXT_STRING_sys', 0, [new Token(0, 'TEXT_STRING', "'it''s'", 0)])));
        self::assertSame('x', $rule->bytes(new Node('json_attribute', 0, [new Node('TEXT_STRING_sys', 0, [new Token(0, 'TEXT_STRING', "'x'", 0)])])));
        self::assertSame([], $leaves->all());
    }

    public function testTextLowersStringsAtPositionsThatAreNotExpressions(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new LiteralRule(new Lowering($platform->productions($profile), new Leaves(), $profile));
        $plain = $rule->text(new Node('TEXT_STRING_filesystem', 0, [new Token(0, 'TEXT_STRING', "'/tmp/f'", 0)]));
        $hex = $rule->text(new Node('text_string', 1, [new Token(0, 'HEX_NUM', "X'41'", 0)]));
        $bits = $rule->text(new Node('text_string', 2, [new Token(0, 'BIN_NUM', '0b1', 0)]));

        self::assertSame('/tmp/f', $plain->value);
        self::assertNull($plain->radix);
        self::assertSame('41', $hex->value);
        self::assertSame(Radix::Hexadecimal, $hex->radix);
        self::assertSame(Radix::Bit, $bits->radix);
    }

    public function testTextsFlattensAStringList(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new LiteralRule(new Lowering($platform->productions($profile), new Leaves(), $profile));
        $string = static fn (string $text): Node => new Node('TEXT_STRING_sys', 0, [new Token(0, 'TEXT_STRING', $text, 0)]);
        $list = new Node('TEXT_STRING_sys_list', 1, [new Node('TEXT_STRING_sys_list', 0, [$string("'a'")]), new Token(0, ',', ',', 0), $string("'b'")]);
        $texts = $rule->texts($list);

        self::assertCount(2, $texts);
        self::assertSame('a', $texts[0]->value);
        self::assertSame('b', $texts[1]->value);
    }

    public function testParameterLowersAMarkerOfEitherStyle(): void
    {
        self::assertSame('SELECT ?', (new Semantics(Dialect::MySql))->analyze('SELECT ?')->toString());
        self::assertSame('SELECT :id', (new Semantics(Dialect::MySql, null, null, ParameterStyle::Named))->analyze('SELECT :id')->toString());
    }
}
