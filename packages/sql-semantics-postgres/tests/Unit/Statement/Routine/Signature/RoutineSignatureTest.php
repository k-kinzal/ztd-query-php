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
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\DefaultSpelling;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\FunctionParameter;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\ParameterMode;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\RoutineSignature;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(RoutineSignature::class)]
#[Small]
final class RoutineSignatureTest extends TestCase
{
    public function testDeriveClauseDerivesTheArguments(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new RoutineSignature(new DottedName([new Name('f')]), [new FunctionParameter(new TypeName(new NamedDesignation(new DottedName([new Name('int4')]))))]))->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesTheArgumentListOnlyWhenWritten(): void
    {
        $listed = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new RoutineSignature(new DottedName([new Name('s'), new Name('f')]), [new FunctionParameter(new TypeName(new NamedDesignation(new DottedName([new Name('int4')]))), new Name('a'), ParameterMode::Out)]))->render($listed);
        $empty = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new RoutineSignature(new DottedName([new Name('f')]), []))->render($empty);
        $bare = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new RoutineSignature(new DottedName([new Name('f')])))->render($bare);
        self::assertSame(['s.f (OUT a int4)', 'f ()', 'f'], [(new Lexical())->join($listed->pieces()), (new Lexical())->join($empty->pieces()), (new Lexical())->join($bare->pieces())]);
    }

    public function testRejectsADefaultValue(): void
    {
        $this->expectExceptionMessage('An argument of a signature has no default value.');
        new RoutineSignature(new DottedName([new Name('f')]), [new FunctionParameter(new TypeName(new NamedDesignation(new DottedName([new Name('int4')]))), null, null, false, new Constant(new IntegerConstant('1')), DefaultSpelling::Keyword)]);
    }
}
