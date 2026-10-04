<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Mode;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\BinaryCast;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Concatenation;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Binary;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(BinaryCast::class)]
#[Medium]
final class BinaryCastTest extends TestCase
{
    public function testDeriveScalarIsABinaryString(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-5.7.44', null, ParameterStyle::Native), null, [], true));
        $fact = $derivation->scalar(new BinaryCast(new NumberLiteral('1')), $derivation->environment());

        self::assertEquals([new Known(new Binary(BinaryKind::VarBinary)), Nullability::NotNull], [$fact->type, $fact->nullability]);
    }

    public function testRenderCastsAPrefixOperandWithoutParentheses(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', Mode::fromString('PIPES_AS_CONCAT'), ParameterStyle::Native)));
        (new Concatenation(new BinaryCast(new Unary(UnaryOperator::Minus, new NumberLiteral('1'))), new NumberLiteral('2')))->render($out);

        self::assertSame('BINARY - 1 || 2', (new Lexical())->join($out->pieces()));
    }

    public function testAConcatenationOperandIsRejected(): void
    {
        $this->expectExceptionMessage('The operand of BINARY needs a grouping to keep its place.');

        new BinaryCast(new Concatenation(new NumberLiteral('1'), new NumberLiteral('2')));
    }
}
