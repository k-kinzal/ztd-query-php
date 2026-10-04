<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Operator\MemberPurpose;
use SqlSemantics\Platform\PostgreSql\Statement\Operator\OperatorMember;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\OperatorArity;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\OperatorSignature;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(OperatorMember::class)]
#[Small]
final class OperatorMemberTest extends TestCase
{
    public function testDeriveClauseDerivesTheOperandTypes(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new OperatorMember(new IntegerConstant('1'), new OperatorSignature(new OperatorName(new Name('<')), OperatorArity::Binary, [new TypeName(new NamedDesignation(new DottedName([new Name('int4')]))), new TypeName(new NamedDesignation(new DottedName([new Name('int4')])))])))->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesStrategyOperatorAndPurpose(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new OperatorMember(new IntegerConstant('3'), new OperatorName(new Name('<->')), new MemberPurpose(new DottedName([new Name('f')]))))->render($out);
        self::assertSame('OPERATOR 3 <-> FOR ORDER BY f', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsTheExplicitSyntax(): void
    {
        $this->expectExceptionMessage('An operator class names an operator without OPERATOR(...).');
        new OperatorMember(new IntegerConstant('1'), new OperatorName(new Name('<'), [], true));
    }
}
