<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Subquery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\ArrayConstructor;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\ArrayItems;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\OperandMismatch;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\QuantifiedArray;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\Quantifier;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(QuantifiedArray::class)]
#[Small]
final class QuantifiedArrayTest extends TestCase
{
    public function testDeriveScalarComparesWithTheElementType(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $array = new ArrayConstructor(new ArrayItems([new Constant(new IntegerConstant('2'))]));
        $fact = $derivation->scalar(new QuantifiedArray(new Constant(new IntegerConstant('1')), new OperatorName(new Name('=')), Quantifier::Any, $array), $derivation->environment());
        self::assertEquals(new Known(Builtin::Bool), $fact->type);
    }

    public function testDeriveScalarReportsANonArray(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new QuantifiedArray(new Constant(new IntegerConstant('1')), new OperatorName(new Name('=')), Quantifier::Any, new Constant(new IntegerConstant('2'))), $derivation->environment());
        self::assertInstanceOf(OperandMismatch::class, $fact->type instanceof Invalid ? $fact->type->cause : null);
    }

    public function testRenderWritesTheQuantifier(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new QuantifiedArray(new NullLiteral(), new OperatorName(new Name('<>')), Quantifier::All, new NullLiteral()))->render($out);
        self::assertSame('NULL <> ALL (NULL)', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsAScalarSubquery(): void
    {
        $this->expectExceptionMessage('A scalar subquery after ANY or ALL is read as a subquery comparison.');
        new QuantifiedArray(new NullLiteral(), new OperatorName(new Name('=')), Quantifier::Any, new ScalarSubquery(self::createStub(Query::class)));
    }
}
