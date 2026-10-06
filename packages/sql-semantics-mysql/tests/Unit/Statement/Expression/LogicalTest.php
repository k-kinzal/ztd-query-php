<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Logical;
use SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Logical::class)]
#[Medium]
final class LogicalTest extends TestCase
{
    public function testDeriveScalarIsAnIntegerThatIsNullWhenAnOperandIs(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        $and = new Logical(LogicalOperator::And, new NumberLiteral('1'), new NumberLiteral('2'));
        $or = new Logical(LogicalOperator::Or, new NumberLiteral('1'), new NullLiteral());

        self::assertEquals(new Known(new Integral(IntegralKind::BigInt)), $derivation->scalar($and, $derivation->environment())->type);
        self::assertSame(Nullability::NotNull, $derivation->scalar($and, $derivation->environment())->nullability);
        self::assertSame(Nullability::Nullable, $derivation->scalar($or, $derivation->environment())->nullability);
    }

    public function testRenderWritesTheKeywordAndKeepsTheLeftAssociation(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        (new Logical(LogicalOperator::Or, new Logical(LogicalOperator::Or, new NumberLiteral('1'), new NumberLiteral('2')), new Logical(LogicalOperator::And, new NumberLiteral('3'), new NumberLiteral('4'))))->render($out);

        self::assertSame('1 OR 2 OR 3 AND 4', (new Lexical())->join($out->pieces()));
    }

    public function testAWeakerLeftOperandIsRejected(): void
    {
        $this->expectExceptionMessage('The left operand of AND needs a grouping to keep its place.');

        new Logical(LogicalOperator::And, new Logical(LogicalOperator::Xor, new NumberLiteral('1'), new NumberLiteral('2')), new NumberLiteral('3'));
    }

    public function testAnEqualRightOperandIsRejected(): void
    {
        $this->expectExceptionMessage('The right operand of OR needs a grouping to keep its place.');

        new Logical(LogicalOperator::Or, new NumberLiteral('1'), new Logical(LogicalOperator::Or, new NumberLiteral('2'), new NumberLiteral('3')));
    }
}
