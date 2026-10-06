<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine;

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
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(FunctionParameter::class)]
#[Small]
final class FunctionParameterTest extends TestCase
{
    public function testInputIsTrueWithoutAMode(): void
    {
        self::assertTrue((new FunctionParameter(new TypeName(new NamedDesignation(new DottedName([new Name('int4')])))))->input());
    }

    public function testOutputFollowsTheMode(): void
    {
        $type = new TypeName(new NamedDesignation(new DottedName([new Name('int4')])));
        self::assertTrue((new FunctionParameter($type, null, ParameterMode::InOut))->output());
        self::assertFalse((new FunctionParameter($type))->output());
    }

    public function testDeriveClauseDerivesTheDefaultValue(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $default = new Constant(new IntegerConstant('1'));
        (new FunctionParameter(new TypeName(new NamedDesignation(new DottedName([new Name('int4')]))), null, null, false, $default, DefaultSpelling::EqualsSign))->deriveClause($derivation, $derivation->environment());
        self::assertTrue($derivation->facts()->covers($default));
    }

    public function testRenderWritesTheNameBeforeTheModeWhenWrittenSo(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new FunctionParameter(new TypeName(new NamedDesignation(new DottedName([new Name('int4')]))), new Name('a'), ParameterMode::InAndOut, true, new Constant(new IntegerConstant('1')), DefaultSpelling::Keyword))->render($out);
        self::assertSame('a IN OUT int4 DEFAULT 1', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsANameFirstWithoutAMode(): void
    {
        $this->expectExceptionMessage('A parameter name is written before the mode only when both are written.');
        new FunctionParameter(new TypeName(new NamedDesignation(new DottedName([new Name('int4')]))), new Name('a'), null, true);
    }

    public function testRejectsASpellingWithoutADefault(): void
    {
        $this->expectExceptionMessage('A default spelling is given exactly with a default value.');
        new FunctionParameter(new TypeName(new NamedDesignation(new DottedName([new Name('int4')]))), null, null, false, null, DefaultSpelling::Keyword);
    }
}
