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
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Step\AllFields;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(AllFields::class)]
#[Small]
final class AllFieldsTest extends TestCase
{
    public function testFieldIsNone(): void
    {
        self::assertNull((new AllFields())->field());
    }

    public function testDeriveClauseRecordsNothing(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new AllFields())->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesTheStar(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new AllFields())->render($out);
        self::assertSame('.*', (new Lexical())->join($out->pieces()));
    }
}
