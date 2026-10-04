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
use SqlSemantics\Platform\PostgreSql\Statement\Operator\FunctionMember;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\RoutineSignature;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(FunctionMember::class)]
#[Small]
final class FunctionMemberTest extends TestCase
{
    public function testDeriveClauseDerivesTypesAndSignature(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new FunctionMember(new IntegerConstant('1'), new RoutineSignature(new DottedName([new Name('f')]), []), [new TypeName(new NamedDesignation(new DottedName([new Name('int4')])))]))->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesTheOperandTypes(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new FunctionMember(new IntegerConstant('2'), new RoutineSignature(new DottedName([new Name('f')])), [new TypeName(new NamedDesignation(new DottedName([new Name('int4')]))), new TypeName(new NamedDesignation(new DottedName([new Name('int4')])))]))->render($out);
        self::assertSame('FUNCTION 2 (int4, int4) f', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsAnEmptyTypeList(): void
    {
        $this->expectExceptionMessage('The operand types of a support function are at least one type name.');
        new FunctionMember(new IntegerConstant('1'), new RoutineSignature(new DottedName([new Name('f')])), []);
    }
}
