<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Interval;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\IntervalArithmetic;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(IntervalArithmetic::class)]
#[Medium]
final class IntervalArithmeticTest extends TestCase
{
    public function testDeriveScalarKeepsADateForADateUnit(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        $fact = $derivation->scalar(new IntervalArithmetic(new TemporalLiteral(TemporalForm::Date, '2024-01-31'), new Interval(new NumberLiteral('1'), IntervalUnit::Month), true), $derivation->environment());

        self::assertEquals([new Known(new Temporal(TemporalKind::Date)), Nullability::Nullable], [$fact->type, $fact->nullability]);
    }

    public function testRenderWritesTheSignBeforeTheInterval(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        (new IntervalArithmetic(new NumberLiteral('1'), new Interval(new NumberLiteral('2'), IntervalUnit::Day), true))->render($out);

        self::assertSame('1 - INTERVAL 2 DAY', (new Lexical())->join($out->pieces()));
    }

    public function testAMultiplicativeOperandIsAccepted(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        (new IntervalArithmetic(new Arithmetic(ArithmeticOperator::Multiply, new NumberLiteral('1'), new NumberLiteral('2')), new Interval(new NumberLiteral('3'), IntervalUnit::Day)))->render($out);

        self::assertSame('1 * 2 + INTERVAL 3 DAY', (new Lexical())->join($out->pieces()));
    }

    public function testABitOperandIsRejected(): void
    {
        $this->expectExceptionMessage('The operand of interval arithmetic needs a grouping to keep its place.');

        new IntervalArithmetic(new Arithmetic(ArithmeticOperator::BitOr, new NumberLiteral('1'), new NumberLiteral('2')), new Interval(new NumberLiteral('3'), IntervalUnit::Day));
    }
}
