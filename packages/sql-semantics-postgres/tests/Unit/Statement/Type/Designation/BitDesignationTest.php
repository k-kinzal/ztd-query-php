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
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\BitDesignation;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(BitDesignation::class)]
#[Small]
final class BitDesignationTest extends TestCase
{
    public function testTypeFactDefaultsToOneBitAsATypeOnly(): void
    {
        $bit = new BitDesignation(false);
        $type = $bit->typeFact((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true), false);
        self::assertInstanceOf(Known::class, $type);
        self::assertSame('bit(1)', $type->descriptor->name());
        self::assertEquals(new Known(Builtin::Bit), $bit->typeFact((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true), true));
        self::assertEquals(new Known(Builtin::Varbit), (new BitDesignation(true))->typeFact((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true), false));
    }

    public function testCatalogNameFollowsVarying(): void
    {
        self::assertSame(['bit', 'varbit'], [(new BitDesignation(false))->catalogName()->value, (new BitDesignation(true))->catalogName()->value]);
    }

    public function testDeriveClauseDerivesTheModifiers(): void
    {
        $modifier = new Constant(new IntegerConstant('3'));
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new BitDesignation(true, [$modifier]))->deriveClause($derivation, $derivation->environment());
        self::assertTrue($derivation->facts()->covers($modifier));
    }

    public function testRenderWritesVaryingAndTheLength(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new BitDesignation(true, [new Constant(new IntegerConstant('8'))]))->render($out);
        self::assertSame('BIT VARYING (8)', (new Lexical())->join($out->pieces()));
    }
}
