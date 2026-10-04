<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\DistinctTest;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\NullTest;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(DistinctTest::class)]
#[Small]
final class DistinctTestTest extends TestCase
{
    public function testDeriveScalarIsNeverNull(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new DistinctTest(new Constant(new IntegerConstant('1')), new NullLiteral(), false), $derivation->environment());
        self::assertEquals(new Known(Builtin::Bool), $fact->type);
        self::assertSame(Nullability::NotNull, $fact->nullability);
    }

    public function testRenderWritesTheNegatedForm(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new DistinctTest(new NullLiteral(), new NullLiteral(), true))->render($out);
        self::assertSame('NULL IS NOT DISTINCT FROM NULL', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsANullTestOnTheRight(): void
    {
        $this->expectExceptionMessage('The right operand needs parentheses to keep its place.');
        new DistinctTest(new NullLiteral(), new NullTest(new NullLiteral(), false), false);
    }
}
