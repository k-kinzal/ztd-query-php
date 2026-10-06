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
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\DecimalDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\DecimalKeyword;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(DecimalDesignation::class)]
#[Small]
final class DecimalDesignationTest extends TestCase
{
    public function testTypeFactAppliesPrecisionAndScale(): void
    {
        $fact = (new DecimalDesignation(DecimalKeyword::Decimal, [new Constant(new IntegerConstant('10')), new Constant(new IntegerConstant('2'))]))->typeFact((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true), false);
        self::assertInstanceOf(Known::class, $fact);
        self::assertSame('numeric(10,2)', $fact->descriptor->name());
        self::assertEquals(new Known(Builtin::Numeric), (new DecimalDesignation(DecimalKeyword::Dec))->typeFact((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true), false));
    }

    public function testCatalogNameIsNumeric(): void
    {
        self::assertSame('numeric', (new DecimalDesignation(DecimalKeyword::Dec))->catalogName()->value);
    }

    public function testDeriveClauseDerivesTheModifiers(): void
    {
        $modifier = new Constant(new IntegerConstant('3'));
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new DecimalDesignation(DecimalKeyword::Numeric, [$modifier]))->deriveClause($derivation, $derivation->environment());
        self::assertTrue($derivation->facts()->covers($modifier));
    }

    public function testRenderWritesTheKeywordAndTheModifiers(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new DecimalDesignation(DecimalKeyword::Dec, [new Constant(new IntegerConstant('10')), new Constant(new IntegerConstant('2'))]))->render($out);
        self::assertSame('DEC (10, 2)', (new Lexical())->join($out->pieces()));
    }
}
