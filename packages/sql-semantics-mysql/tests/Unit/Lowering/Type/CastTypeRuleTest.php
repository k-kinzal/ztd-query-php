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
use SqlSemantics\Platform\MySql\Lowering\Type\CastTypeRule;
use SqlSemantics\Platform\MySql\Lowering\Type\TypePartRule;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;

#[CoversClass(CastTypeRule::class)]
#[Medium]
final class CastTypeRuleTest extends TestCase
{
    public function testTargetLowersEveryCastTargetForm(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $rule = new CastTypeRule($lowering, new TypePartRule($lowering));
        $length = new Node('field_length', 3, [new Token(0, '(', '(', 0), new Token(0, 'NUM', '10', 0), new Token(0, ')', ')', 0)]);
        $precision = new Node('precision', 0, [new Token(0, '(', '(', 0), new Token(0, 'NUM', '10', 0), new Token(0, ',', ',', 0), new Token(0, 'NUM', '2', 0), new Token(0, ')', ')', 0)]);
        $char = $rule->target(new Node('cast_type', 1, [new Token(0, 'CHAR_SYM', 'CHAR', 0), new Node('opt_field_length', 1, [$length]), new Node('opt_charset_with_opt_binary', 5, [new Token(0, 'BINARY_SYM', 'BINARY', 0)])]));
        $decimal = $rule->target(new Node('cast_type', 11, [new Token(0, 'DECIMAL_SYM', 'DECIMAL', 0), new Node('float_options', 2, [$precision])]));
        $real = $rule->target(new Node('cast_type', 13, [new Node('real_type', 0, [new Token(0, 'REAL_SYM', 'REAL', 0)])]));
        $double = $rule->target(new Node('cast_type', 13, [new Node('real_type', 1, [new Token(0, 'DOUBLE_SYM', 'DOUBLE', 0), new Node('opt_PRECISION', 0, [])])]));
        $signed = $rule->target(new Node('cast_type', 4, [new Token(0, 'SIGNED_SYM', 'SIGNED', 0), new Token(0, 'INT_SYM', 'INT', 0)]));
        $nchar = $rule->target(new Node('cast_type', 2, [new Node('nchar', 0, [new Token(0, 'NCHAR_SYM', 'NCHAR', 0)]), new Node('opt_field_length', 0, [])]));
        $time = $rule->target(new Node('cast_type', 9, [new Token(0, 'TIME_SYM', 'TIME', 0), new Node('type_datetime_precision', 1, [new Token(0, '(', '(', 0), new Token(0, 'NUM', '3', 0), new Token(0, ')', ')', 0)])]));
        $float = $rule->target(new Node('cast_type', 14, [new Token(0, 'FLOAT_SYM', 'FLOAT', 0), new Node('standard_float_options', 0, [])]));

        self::assertSame(CastKind::Char, $char->kind);
        self::assertSame('10', $char->length);
        self::assertSame(CharsetForm::Binary, $char->charset?->form);
        self::assertSame(CastKind::Decimal, $decimal->kind);
        self::assertSame('2', $decimal->scale);
        self::assertSame(CastKind::Real, $real->kind);
        self::assertSame(CastKind::Double, $double->kind);
        self::assertSame(CastKind::Signed, $signed->kind);
        self::assertSame(CastKind::NationalChar, $nchar->kind);
        self::assertSame('3', $time->length);
        self::assertSame(CastKind::Float, $float->kind);
        self::assertSame(CastKind::Point, $rule->target(new Node('cast_type', 15, [new Token(0, 'POINT_SYM', 'POINT', 0)]))->kind);
    }

    public function testTargetReportsAProductionWithoutARule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $rule = new CastTypeRule($lowering, new TypePartRule($lowering));

        $this->expectExceptionMessage('No semantic rule is implemented for: type: JSON_SYM');

        $rule->target(new Node('type', 36, [new Token(0, 'JSON_SYM', 'JSON', 0)]));
    }
}
