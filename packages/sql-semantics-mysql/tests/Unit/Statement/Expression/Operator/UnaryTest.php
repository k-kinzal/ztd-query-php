<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Collated;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Concatenation;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Floating;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(Unary::class)]
#[Medium]
final class UnaryTest extends TestCase
{
    public function testDeriveScalarTypesEachOperator(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        $plus = $derivation->scalar(new Unary(UnaryOperator::Plus, new StringLiteral(['7'])), $derivation->environment());
        $minus = $derivation->scalar(new Unary(UnaryOperator::Minus, new StringLiteral(['7'])), $derivation->environment());
        $invert = $derivation->scalar(new Unary(UnaryOperator::Invert, new StringLiteral(['7'])), $derivation->environment());
        $not = $derivation->scalar(new Unary(UnaryOperator::Not, new StringLiteral(['7'])), $derivation->environment());

        self::assertEquals([new Known(new Character(CharacterKind::VarChar)), new Known(new Floating(FloatingKind::Double)), new Known(new Integral(IntegralKind::BigInt, null, [NumericModifier::Unsigned])), new Known(new Integral(IntegralKind::BigInt))], [$plus->type, $minus->type, $invert->type, $not->type]);
    }

    public function testRenderNegatesACollatedOperandWithoutParentheses(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        (new Unary(UnaryOperator::Minus, new Unary(UnaryOperator::Minus, new Collated(new NumberLiteral('1'), new Name('utf8mb4_bin')))))->render($out);

        self::assertSame('- - 1 COLLATE utf8mb4_bin', (new Lexical())->join($out->pieces()));
    }

    public function testAConcatenationOperandIsRejected(): void
    {
        $this->expectExceptionMessage('The operand of a prefix operator needs a grouping to keep its place.');

        new Unary(UnaryOperator::Minus, new Concatenation(new NumberLiteral('1'), new NumberLiteral('2')));
    }
}
