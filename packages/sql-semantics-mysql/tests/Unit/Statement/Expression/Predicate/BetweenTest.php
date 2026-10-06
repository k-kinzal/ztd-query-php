<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Between;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Between::class)]
#[Medium]
final class BetweenTest extends TestCase
{
    public function testDeriveScalarIsNullWhenABoundCanBe(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));

        self::assertSame(Nullability::Nullable, $derivation->scalar(new Between(new NumberLiteral('1'), new NullLiteral(), new NumberLiteral('2')), $derivation->environment())->nullability);
        self::assertSame(Nullability::NotNull, $derivation->scalar(new Between(new NumberLiteral('1'), new NumberLiteral('0'), new NumberLiteral('2')), $derivation->environment())->nullability);
    }

    public function testRenderNestsARangeInTheUpperBound(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        (new Between(new NumberLiteral('1'), new NumberLiteral('2'), new Between(new NumberLiteral('3'), new NumberLiteral('4'), new NumberLiteral('5')), true))->render($out);

        self::assertSame('1 NOT BETWEEN 2 AND 3 BETWEEN 4 AND 5', (new Lexical())->join($out->pieces()));
    }

    public function testALowerBoundThatTakesTheAndIsRejected(): void
    {
        $this->expectExceptionMessage('The lower bound of BETWEEN needs a grouping to keep its place.');

        new Between(new NumberLiteral('1'), new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('2'), new VariableAssignment(new UserVariable(new Name('v')), new NumberLiteral('3'))), new NumberLiteral('4'));
    }

    public function testAnUpperBoundRangeOnTheLowerSideIsRejected(): void
    {
        $this->expectExceptionMessage('The lower bound of BETWEEN needs a grouping to keep its place.');

        new Between(new NumberLiteral('1'), new Between(new NumberLiteral('2'), new NumberLiteral('3'), new NumberLiteral('4')), new NumberLiteral('5'));
    }
}
