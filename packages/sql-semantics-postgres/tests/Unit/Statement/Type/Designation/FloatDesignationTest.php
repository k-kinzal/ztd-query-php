<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Designation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\FloatDesignation;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(FloatDesignation::class)]
#[Small]
final class FloatDesignationTest extends TestCase
{
    public function testBuiltinFollowsThePrecision(): void
    {
        self::assertSame(Builtin::Float4, (new FloatDesignation(new IntegerConstant('24')))->builtin());
        self::assertSame(Builtin::Float8, (new FloatDesignation(new IntegerConstant('25')))->builtin());
        self::assertSame(Builtin::Float8, (new FloatDesignation())->builtin());
    }

    public function testTypeFactIsKnown(): void
    {
        self::assertEquals(new Known(Builtin::Float4), (new FloatDesignation(new IntegerConstant('1')))->typeFact((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true), false));
    }

    public function testCatalogNameFollowsThePrecision(): void
    {
        self::assertSame('float4', (new FloatDesignation(new IntegerConstant('1')))->catalogName()->value);
    }

    public function testDeriveClauseRecordsNothing(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new FloatDesignation())->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesThePrecision(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new FloatDesignation(new IntegerConstant('53')))->render($out);
        self::assertSame('FLOAT (53)', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsAPrecisionOfZero(): void
    {
        $this->expectExceptionMessage('A float precision is 1 to 53 binary digits.');
        new FloatDesignation(new IntegerConstant('0'));
    }
}
