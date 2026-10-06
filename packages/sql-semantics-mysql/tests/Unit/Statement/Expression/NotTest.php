<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Mode;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Logical;
use SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Not;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Not::class)]
#[Medium]
final class NotTest extends TestCase
{
    public function testDeriveScalarIsNullWhenTheOperandIs(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));

        self::assertSame(Nullability::Nullable, $derivation->scalar(new Not(new NullLiteral()), $derivation->environment())->nullability);
        self::assertSame(Nullability::NotNull, $derivation->scalar(new Not(new NumberLiteral('1')), $derivation->environment())->nullability);
    }

    public function testDeriveScalarRejectsTheLowNegationUnderHighNotPrecedence(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', Mode::fromString('HIGH_NOT_PRECEDENCE'), ParameterStyle::Native), null, [], true));

        $this->expectExceptionMessage('Under HIGH_NOT_PRECEDENCE, NOT is the tight negation; use the unary NOT.');

        $derivation->scalar(new Not(new NumberLiteral('1')), $derivation->environment());
    }

    public function testRenderNegatesAComparisonWithoutParentheses(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        (new Not(new Comparison(ComparisonOperator::Equal, new NumberLiteral('1'), new NumberLiteral('2'))))->render($out);

        self::assertSame('NOT 1 = 2', (new Lexical())->join($out->pieces()));
    }

    public function testAConjunctionOperandIsRejected(): void
    {
        $this->expectExceptionMessage('The operand of NOT needs a grouping to keep its place.');

        new Not(new Logical(LogicalOperator::And, new NumberLiteral('1'), new NumberLiteral('2')));
    }
}
