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
use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\Between;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\NullTest;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Between::class)]
#[Small]
final class BetweenTest extends TestCase
{
    public function testDeriveScalarIsBoolean(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new Between(new Constant(new IntegerConstant('2')), false, false, new Constant(new IntegerConstant('1')), new NullLiteral()), $derivation->environment());
        self::assertEquals(new Known(Builtin::Bool), $fact->type);
        self::assertSame(Nullability::Nullable, $fact->nullability);
    }

    public function testRenderWritesNotAndSymmetric(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new Between(new NullLiteral(), true, true, new NullLiteral(), new NullLiteral()))->render($out);
        self::assertSame('NULL NOT BETWEEN SYMMETRIC NULL AND NULL', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsALowBoundOutsideBExpr(): void
    {
        $this->expectExceptionMessage('The low bound of BETWEEN needs parentheses: it is a b_expr.');
        new Between(new NullLiteral(), false, false, new NullTest(new NullLiteral(), false), new NullLiteral());
    }

    public function testRejectsATestedComparison(): void
    {
        $comparison = new BinaryOperation(new OperatorName(new Name('=')), new NullLiteral(), new NullLiteral());
        $this->expectExceptionMessage('The tested value needs parentheses to keep its place.');
        new Between($comparison, false, false, new NullLiteral(), new NullLiteral());
    }
}
