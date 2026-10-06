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
use SqlSemantics\Platform\PostgreSql\Statement\Operator\MemberKind;
use SqlSemantics\Platform\PostgreSql\Statement\Operator\MemberRemoval;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(MemberRemoval::class)]
#[Small]
final class MemberRemovalTest extends TestCase
{
    public function testDeriveClauseDerivesTheTypes(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new MemberRemoval(MemberKind::Function, new IntegerConstant('1'), [new TypeName(new NamedDesignation(new DottedName([new Name('int4')])))]))->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesKindNumberAndTypes(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new MemberRemoval(MemberKind::Function, new IntegerConstant('1'), [new TypeName(new NamedDesignation(new DottedName([new Name('int4')])))]))->render($out);
        self::assertSame('FUNCTION 1 (int4)', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsAnEmptyTypeList(): void
    {
        $this->expectExceptionMessage('A removed member names at least one operand type.');
        new MemberRemoval(MemberKind::Operator, new IntegerConstant('1'), []);
    }
}
