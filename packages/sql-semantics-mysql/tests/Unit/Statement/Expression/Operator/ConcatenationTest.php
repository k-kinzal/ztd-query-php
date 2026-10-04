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
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Concatenation;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Binary;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(Concatenation::class)]
#[Medium]
final class ConcatenationTest extends TestCase
{
    public function testDeriveScalarIsABinaryStringWhenAnOperandIs(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', Mode::fromString('PIPES_AS_CONCAT'), ParameterStyle::Native), null, [], true));
        $text = $derivation->scalar(new Concatenation(new StringLiteral(['a']), new NumberLiteral('1')), $derivation->environment());
        $bytes = $derivation->scalar(new Concatenation(new StringLiteral(['a']), new RadixLiteral(Radix::Hexadecimal, '41')), $derivation->environment());

        self::assertEquals([new Known(new Character(CharacterKind::VarChar)), new Known(new Binary(BinaryKind::VarBinary))], [$text->type, $bytes->type]);
    }

    public function testDeriveScalarRejectsTheOperatorWithoutPipesAsConcat(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));

        $this->expectExceptionMessage('The concatenation operator || exists only under PIPES_AS_CONCAT.');

        $derivation->scalar(new Concatenation(new NumberLiteral('1'), new NumberLiteral('2')), $derivation->environment());
    }

    public function testRenderAssociatesToTheLeft(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', Mode::fromString('PIPES_AS_CONCAT'), ParameterStyle::Native)));
        (new Concatenation(new Concatenation(new NumberLiteral('1'), new NumberLiteral('2')), new NumberLiteral('3')))->render($out);

        self::assertSame('1 || 2 || 3', (new Lexical())->join($out->pieces()));
    }

    public function testAConcatenationOnTheRightIsRejected(): void
    {
        $this->expectExceptionMessage('The right operand of || needs a grouping to keep its place.');

        new Concatenation(new NumberLiteral('1'), new Concatenation(new NumberLiteral('2'), new NumberLiteral('3')));
    }
}
