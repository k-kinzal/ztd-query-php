<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Constructor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Lexical;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\CaseBranch;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\CaseExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

#[CoversClass(CaseExpression::class)]
#[Small]
final class CaseExpressionTest extends TestCase
{
    public function testOutputNameIsTheElseNameOrCase(): void
    {
        $named = new CaseExpression(null, [new CaseBranch(new BooleanLiteral(true), new NullLiteral())], new ColumnReference([new Name('a')]));
        $unnamed = new CaseExpression(null, [new CaseBranch(new BooleanLiteral(true), new NullLiteral())]);
        self::assertSame(['a', 'case'], [$named->outputName()?->value, $unnamed->outputName()?->value]);
    }

    public function testDeriveScalarUnifiesTheResults(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $case = new CaseExpression(new Constant(new IntegerConstant('1')), [new CaseBranch(new Constant(new IntegerConstant('1')), new Constant(new StringConstant('2')))], new Constant(new IntegerConstant('3')));
        $fact = $derivation->scalar($case, $derivation->environment());
        self::assertEquals(new Known(Builtin::Int4), $fact->type);
        self::assertSame(Nullability::NotNull, $fact->nullability);
    }

    public function testRenderWritesTheBranchesAndElse(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new CaseExpression(new NullLiteral(), [new CaseBranch(new NullLiteral(), new NullLiteral())], new NullLiteral()))->render($out);
        self::assertSame('CASE NULL WHEN NULL THEN NULL ELSE NULL END', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsNoBranch(): void
    {
        $this->expectExceptionMessage('A CASE expression has at least one WHEN branch.');
        new CaseExpression(null, []);
    }
}
