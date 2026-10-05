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
use SqlSemantics\Platform\MySql\Lowering\Type\TypeRule;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Type\Decimal;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Floating;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\SpatialKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Spatial;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;

#[CoversClass(TypeRule::class)]
#[Medium]
final class TypeRuleTest extends TestCase
{
    public function testTypeLowersAnIntegerWithWidthAndAttributes(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new TypeRule(new Lowering($platform->productions($profile), new Leaves(), $profile));
        $width = new Node('opt_field_length', 1, [new Node('field_length', 3, [new Token(0, '(', '(', 0), new Token(0, 'NUM', '11', 0), new Token(0, ')', ')', 0)])]);
        $options = new Node('field_options', 1, [new Node('field_opt_list', 0, [new Node('field_opt_list', 1, [new Node('field_option', 1, [new Token(0, 'UNSIGNED_SYM', 'UNSIGNED', 0)])]), new Node('field_option', 2, [new Token(0, 'ZEROFILL_SYM', 'ZEROFILL', 0)])])]);
        $type = $rule->type(new Node('type', 0, [new Node('int_type', 4, [new Token(0, 'BIGINT_SYM', 'BIGINT', 0)]), $width, $options]));

        self::assertInstanceOf(Integral::class, $type);
        self::assertSame(IntegralKind::BigInt, $type->kind);
        self::assertSame('11', $type->width);
        self::assertSame([NumericModifier::Unsigned, NumericModifier::Zerofill], $type->modifiers);
        self::assertSame('BIGINT', $type->name());
    }

    public function testTypeReportsAProductionWithoutARule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new TypeRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: int_type: INT_SYM');

        $rule->type(new Node('int_type', 0, [new Token(0, 'INT_SYM', 'INT', 0)]));
    }

    public function testCastTargetLowersACastType(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new TypeRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        self::assertSame(CastKind::Json, $rule->castTarget(new Node('cast_type', 12, [new Token(0, 'JSON_SYM', 'JSON', 0)]))->kind);
    }

    public function testPrecisionLowersAnOptionalLength(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new TypeRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        self::assertNull($rule->precision(new Node('type_datetime_precision', 0, [])));
        self::assertSame('6', $rule->precision(new Node('type_datetime_precision', 1, [new Token(0, '(', '(', 0), new Token(0, 'NUM', '6', 0), new Token(0, ')', ')', 0)])));
    }

    public function testNumericLowersFloatingAndFixedPointTypes(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new TypeRule(new Lowering($platform->productions($profile), new Leaves(), $profile));
        $precision = new Node('precision', 0, [new Token(0, '(', '(', 0), new Token(0, 'NUM', '10', 0), new Token(0, ',', ',', 0), new Token(0, 'NUM', '2', 0), new Token(0, ')', ')', 0)]);
        $none = new Node('field_options', 0, []);
        $double = $rule->numeric(new Form(new Node('type', 1, [new Node('real_type', 1, [new Token(0, 'DOUBLE_SYM', 'DOUBLE', 0), new Node('opt_PRECISION', 1, [new Token(0, 'PRECISION', 'PRECISION', 0)])]), new Node('opt_precision', 1, [$precision]), $none]), 'type: real_type opt_precision field_options'));
        $decimal = $rule->numeric(new Form(new Node('type', 2, [new Node('numeric_type', 1, [new Token(0, 'DECIMAL_SYM', 'DECIMAL', 0)]), new Node('float_options', 2, [$precision]), $none]), 'type: numeric_type float_options field_options'));
        $float = $rule->numeric(new Form(new Node('type', 2, [new Node('numeric_type', 0, [new Token(0, 'FLOAT_SYM', 'FLOAT', 0)]), new Node('float_options', 0, []), $none]), 'type: numeric_type float_options field_options'));

        self::assertInstanceOf(Floating::class, $double);
        self::assertSame(FloatingKind::Double, $double->kind);
        self::assertSame('10', $double->precision);
        self::assertSame('2', $double->scale);
        self::assertInstanceOf(Decimal::class, $decimal);
        self::assertSame('10', $decimal->precision);
        self::assertInstanceOf(Floating::class, $float);
        self::assertNull($float->precision);
        self::assertNull($rule->numeric(new Form(new Node('type', 17, [new Token(0, 'DATE_SYM', 'DATE', 0)]), 'type: DATE_SYM')));
    }

    public function testFractionBuildsATypeFromItsNumbersAndAttributes(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new TypeRule(new Lowering($platform->productions($profile), new Leaves(), $profile));
        $form = new Form(new Node('type', 2, [new Node('numeric_type', 2, [new Token(0, 'NUMERIC_SYM', 'NUMERIC', 0)]), new Node('float_options', 1, [new Node('field_length', 3, [new Token(0, '(', '(', 0), new Token(0, 'NUM', '5', 0), new Token(0, ')', ')', 0)])]), new Node('field_options', 1, [new Node('field_opt_list', 1, [new Node('field_option', 1, [new Token(0, 'UNSIGNED_SYM', 'UNSIGNED', 0)])])])]), 'type: numeric_type float_options field_options');
        $decimal = $rule->fraction(null, $form);
        $float = $rule->fraction(FloatingKind::Float, $form);

        self::assertInstanceOf(Decimal::class, $decimal);
        self::assertSame('5', $decimal->precision);
        self::assertNull($decimal->scale);
        self::assertSame([NumericModifier::Unsigned], $decimal->modifiers);
        self::assertInstanceOf(Floating::class, $float);
        self::assertSame(FloatingKind::Float, $float->kind);
    }

    public function testRealLowersEveryRealKeyword(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new TypeRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        self::assertSame(FloatingKind::Real, $rule->real(new Node('real_type', 0, [new Token(0, 'REAL_SYM', 'REAL', 0)])));
        self::assertSame(FloatingKind::Double, $rule->real(new Node('real_type', 1, [new Token(0, 'DOUBLE_SYM', 'DOUBLE', 0), new Node('opt_PRECISION', 0, [])])));
    }

    public function testKeywordLowersElementaryTemporalAndSpatialTypes(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new TypeRule(new Lowering($platform->productions($profile), new Leaves(), $profile));
        $six = new Node('type_datetime_precision', 1, [new Token(0, '(', '(', 0), new Token(0, 'NUM', '6', 0), new Token(0, ')', ')', 0)]);
        $json = $rule->keyword(new Form(new Node('type', 36, [new Token(0, 'JSON_SYM', 'JSON', 0)]), 'type: JSON_SYM'));
        $bit = $rule->keyword(new Form(new Node('type', 4, [new Token(0, 'BIT_SYM', 'BIT', 0), new Node('field_length', 3, [new Token(0, '(', '(', 0), new Token(0, 'NUM', '8', 0), new Token(0, ')', ')', 0)])]), 'type: BIT_SYM field_length'));
        $time = $rule->keyword(new Form(new Node('type', 18, [new Token(0, 'TIME_SYM', 'TIME', 0), $six]), 'type: TIME_SYM type_datetime_precision'));
        $year = $rule->keyword(new Form(new Node('type', 16, [new Token(0, 'YEAR_SYM', 'YEAR', 0), new Node('opt_field_length', 0, []), new Node('field_options', 0, [])]), 'type: YEAR_SYM opt_field_length field_options'));
        $point = $rule->keyword(new Form(new Node('type', 23, [new Node('spatial_type', 2, [new Token(0, 'POINT_SYM', 'POINT', 0)])]), 'type: spatial_type'));

        self::assertInstanceOf(Elementary::class, $json);
        self::assertSame(ElementaryKind::Json, $json->kind);
        self::assertInstanceOf(Elementary::class, $bit);
        self::assertSame('8', $bit->length);
        self::assertInstanceOf(Temporal::class, $time);
        self::assertSame(TemporalKind::Time, $time->kind);
        self::assertSame('6', $time->precision);
        self::assertInstanceOf(Temporal::class, $year);
        self::assertSame(TemporalKind::Year, $year->kind);
        self::assertInstanceOf(Spatial::class, $point);
        self::assertSame(SpatialKind::Point, $point->kind);
        self::assertNull($rule->keyword(new Form(new Node('type', 36, []), 'type: CHAR_SYM opt_charset_with_opt_binary')));
    }

    public function testDoublePrecisionTellsWhetherPrecisionIsWritten(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new TypeRule(new Lowering($platform->productions($profile), new Leaves(), $profile));
        $parser = $platform->parser($profile);

        self::assertTrue($rule->doublePrecision($parser->parse('SELECT CAST(a AS DOUBLE PRECISION)')->find('real_type')[0]));
        self::assertFalse($rule->doublePrecision($parser->parse('SELECT CAST(a AS DOUBLE)')->find('real_type')[0]));
    }
}
