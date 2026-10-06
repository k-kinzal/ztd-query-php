<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Signature;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\OperatorArity;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\OperatorSignature;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(OperatorSignature::class)]
#[Small]
final class OperatorSignatureTest extends TestCase
{
    public function testLeftAnswersTheLeftOperand(): void
    {
        $left = new TypeName(new NamedDesignation(new DottedName([new Name('int4')])));
        self::assertSame([$left, null], [(new OperatorSignature(new OperatorName(new Name('!')), OperatorArity::Postfix, [$left]))->left(), (new OperatorSignature(new OperatorName(new Name('-')), OperatorArity::Prefix, [new TypeName(new NamedDesignation(new DottedName([new Name('int4')])))]))->left()]);
    }

    public function testRightAnswersTheRightOperand(): void
    {
        $right = new TypeName(new NamedDesignation(new DottedName([new Name('text')])));
        self::assertSame([$right, null], [(new OperatorSignature(new OperatorName(new Name('=')), OperatorArity::Binary, [new TypeName(new NamedDesignation(new DottedName([new Name('int4')]))), $right]))->right(), (new OperatorSignature(new OperatorName(new Name('=')), OperatorArity::Incomplete, [new TypeName(new NamedDesignation(new DottedName([new Name('int4')])))]))->right()]);
    }

    public function testDeriveClauseReportsASingleType(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new OperatorSignature(new OperatorName(new Name('=')), OperatorArity::Incomplete, [new TypeName(new NamedDesignation(new DottedName([new Name('int4')])))]))->deriveClause($derivation, $derivation->environment());
        self::assertEquals([new RoutineProblem(RoutineProblemKind::MissingOperatorArgument)], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesNoneForAMissingOperand(): void
    {
        $prefix = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new OperatorSignature(new OperatorName(new Name('-'), [new Name('s')]), OperatorArity::Prefix, [new TypeName(new NamedDesignation(new DottedName([new Name('int4')])))]))->render($prefix);
        $postfix = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new OperatorSignature(new OperatorName(new Name('!')), OperatorArity::Postfix, [new TypeName(new NamedDesignation(new DottedName([new Name('int4')])))]))->render($postfix);
        self::assertSame(['s.- (NONE, int4)', '! (int4, NONE)'], [(new Lexical())->join($prefix->pieces()), (new Lexical())->join($postfix->pieces())]);
    }

    public function testRejectsTheExplicitSyntax(): void
    {
        $this->expectExceptionMessage('An operator signature names the operator without the OPERATOR(...) syntax.');
        new OperatorSignature(new OperatorName(new Name('='), [], true), OperatorArity::Binary, [new TypeName(new NamedDesignation(new DottedName([new Name('int4')]))), new TypeName(new NamedDesignation(new DottedName([new Name('int4')])))]);
    }

    public function testRejectsAWrongTypeCount(): void
    {
        $this->expectExceptionMessage('The number of operand types matches how they are written.');
        new OperatorSignature(new OperatorName(new Name('=')), OperatorArity::Binary, [new TypeName(new NamedDesignation(new DottedName([new Name('int4')])))]);
    }
}
