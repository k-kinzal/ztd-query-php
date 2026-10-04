<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\UnqualifiedName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(UnqualifiedName::class)]
#[Small]
final class UnqualifiedNameTest extends TestCase
{
    public function testDeriveClauseRecordsNothing(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new UnqualifiedName(new Name('s')))->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderQuotesAsAColumnName(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new UnqualifiedName(new Name('Select')))->render($out);
        self::assertSame('"Select"', (new Lexical())->join($out->pieces()));
    }
}
