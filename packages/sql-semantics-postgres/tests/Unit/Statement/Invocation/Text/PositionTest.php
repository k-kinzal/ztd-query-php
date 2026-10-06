<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\Negation;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text\Position;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Position::class)]
#[Small]
final class PositionTest extends TestCase
{
    public function testRejectsAnOperandThatIsNotRestricted(): void
    {
        $this->expectExceptionMessage('The operands of POSITION are restricted expressions; group an operand that is not.');
        new Position(new Negation(new BooleanLiteral(true)), new NullLiteral());
    }

    public function testOutputNameIsPosition(): void
    {
        self::assertSame('position', (new Position(new NullLiteral(), new NullLiteral()))->outputName()->value);
    }

    public function testDeriveScalarIsInteger(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new Position(new Constant(new StringConstant('b')), new Constant(new StringConstant('abc'))), $derivation->environment());
        self::assertEquals(new ScalarFact(new Known(Builtin::Int4), Nullability::NotNull), $fact);
    }

    public function testRenderWritesTheSubstringFirst(): void
    {
        $position = new Position(new Constant(new StringConstant('b')), new Constant(new StringConstant('abc')));
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $position->render($out);
        self::assertSame('POSITION(\'b\' IN \'abc\')', (new Lexical())->join($out->pieces()));
    }
}
