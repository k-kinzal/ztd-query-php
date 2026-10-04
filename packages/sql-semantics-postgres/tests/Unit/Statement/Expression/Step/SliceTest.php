<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Step;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Step\Slice;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(Slice::class)]
#[Small]
final class SliceTest extends TestCase
{
    public function testFieldIsNone(): void
    {
        self::assertNull((new Slice(null, null))->field());
    }

    public function testDeriveClauseDerivesTheWrittenBounds(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $upper = new Constant(new IntegerConstant('2'));
        (new Slice(null, $upper))->deriveClause($derivation, $derivation->environment());
        self::assertEquals(new Known(Builtin::Int4), $derivation->facts()->scalar($upper)->type);
    }

    public function testRenderWritesTheBounds(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new Slice(new Constant(new IntegerConstant('1')), null))->render($out);
        self::assertSame('[1 :]', (new Lexical())->join($out->pieces()));
    }
}
