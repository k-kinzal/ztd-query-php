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
use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\Negation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\NullTest;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\NullTestSpelling;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(NullTest::class)]
#[Small]
final class NullTestTest extends TestCase
{
    public function testDeriveScalarIsANonNullBoolean(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new NullTest(new NullLiteral(), true), $derivation->environment());
        self::assertEquals(new Known(Builtin::Bool), $fact->type);
        self::assertSame(Nullability::NotNull, $fact->nullability);
    }

    public function testRenderWritesBothSpellings(): void
    {
        $keywords = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new NullTest(new NullLiteral(), true))->render($keywords);
        $postfix = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new NullTest(new NullTest(new NullLiteral(), false, NullTestSpelling::Postfix), true, NullTestSpelling::Postfix))->render($postfix);
        self::assertSame(['NULL IS NOT NULL', 'NULL ISNULL NOTNULL'], [(new Lexical())->join($keywords->pieces()), (new Lexical())->join($postfix->pieces())]);
    }

    public function testAcceptsAComparison(): void
    {
        $comparison = new BinaryOperation(new OperatorName(new Name('=')), new NullLiteral(), new NullLiteral());
        self::assertSame($comparison, (new NullTest($comparison, false))->operand);
    }

    public function testRejectsANegation(): void
    {
        $this->expectExceptionMessage('The tested value needs parentheses to keep its place.');
        new NullTest(new Negation(new NullLiteral()), false);
    }
}
