<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Lexical;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Toggle;

#[CoversClass(Toggle::class)]
#[Small]
final class ToggleTest extends TestCase
{
    public function testTextIsTheLowerCaseWordTheServerReceives(): void
    {
        self::assertSame(['true', 'false', 'on'], array_map(static fn (Toggle $toggle): string => $toggle->text(), Toggle::cases()));
    }

    public function testDeriveClauseRecordsNothing(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        Toggle::On->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesTheKeyword(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        Toggle::False->render($out);
        self::assertSame('FALSE', (new Lexical())->join($out->pieces()));
    }
}
