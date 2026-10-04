<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\Negation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\BooleanTest;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\BooleanTestKind;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(BooleanTest::class)]
#[Small]
final class BooleanTestTest extends TestCase
{
    public function testDeriveScalarReportsANonBooleanOperand(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new BooleanTest(new Constant(new IntegerConstant('1')), BooleanTestKind::IsTrue), $derivation->environment());
        self::assertInstanceOf(Invalid::class, $fact->type);
        self::assertSame(Nullability::NotNull, $fact->nullability);
    }

    public function testRenderWritesTheTest(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new BooleanTest(new NullLiteral(), BooleanTestKind::IsNotFalse))->render($out);
        self::assertSame('NULL IS NOT FALSE', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsANegation(): void
    {
        $this->expectExceptionMessage('The tested value needs parentheses to keep its place.');
        new BooleanTest(new Negation(new NullLiteral()), BooleanTestKind::IsTrue);
    }
}
