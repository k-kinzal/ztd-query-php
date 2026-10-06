<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Expression\Logical;
use SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Interval;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\IntervalAddition;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(IntervalAddition::class)]
#[Medium]
final class IntervalAdditionTest extends TestCase
{
    public function testDeriveScalarMakesADateTimeForATimeUnit(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));

        self::assertEquals(new Known(new Temporal(TemporalKind::DateTime)), $derivation->scalar(new IntervalAddition(new Interval(new NumberLiteral('1'), IntervalUnit::Hour), new TemporalLiteral(TemporalForm::Date, '2024-01-31')), $derivation->environment())->type);
    }

    public function testRenderLetsTheOperandExtendOverAComparison(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        (new IntervalAddition(new Interval(new NumberLiteral('1'), IntervalUnit::Day), new Comparison(ComparisonOperator::Equal, new NumberLiteral('2'), new NumberLiteral('3'))))->render($out);

        self::assertSame('INTERVAL 1 DAY + 2 = 3', (new Lexical())->join($out->pieces()));
    }

    public function testAConjunctionOperandIsRejected(): void
    {
        $this->expectExceptionMessage('The operand of a leading interval needs a grouping to keep its place.');

        new IntervalAddition(new Interval(new NumberLiteral('1'), IntervalUnit::Day), new Logical(LogicalOperator::And, new NumberLiteral('2'), new NumberLiteral('3')));
    }
}
