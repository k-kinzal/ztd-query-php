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
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\MemberOf;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(MemberOf::class)]
#[Medium]
final class MemberOfTest extends TestCase
{
    public function testDeriveScalarIsNullWhenTheArrayCanBe(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.0.44', null, ParameterStyle::Native), null, [], true));

        self::assertSame(Nullability::Nullable, $derivation->scalar(new MemberOf(new NumberLiteral('1'), new NullLiteral()), $derivation->environment())->nullability);
    }

    public function testDeriveScalarRejectsTheTestBeforeMySql80(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-5.7.44', null, ParameterStyle::Native), null, [], true));

        $this->expectExceptionMessage('MEMBER OF needs MySQL 8.0 or later.');

        $derivation->scalar(new MemberOf(new NumberLiteral('1'), new StringLiteral(['[1]'])), $derivation->environment());
    }

    public function testRenderWritesOfAndTheArrayInParentheses(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        (new MemberOf(new NumberLiteral('1'), new StringLiteral(['[1]'])))->render($out);

        self::assertSame("1 MEMBER OF ('[1]')", (new Lexical())->join($out->pieces()));
    }

    public function testAnArithmeticArrayIsRejected(): void
    {
        $this->expectExceptionMessage('The array of MEMBER OF needs a grouping to keep its place.');

        new MemberOf(new NumberLiteral('1'), new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new NumberLiteral('2')));
    }
}
