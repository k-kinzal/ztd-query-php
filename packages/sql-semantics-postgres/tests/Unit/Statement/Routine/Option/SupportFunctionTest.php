<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Lexical;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\SupportFunction;

#[CoversClass(SupportFunction::class)]
#[Small]
final class SupportFunctionTest extends TestCase
{
    public function testAlterableIsTrue(): void
    {
        self::assertTrue((new SupportFunction(new DottedName([new Name('s')])))->alterable());
    }

    public function testSettingIsSupport(): void
    {
        self::assertSame('support', (new SupportFunction(new DottedName([new Name('s')])))->setting());
    }

    public function testDeriveClauseRecordsNothing(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new SupportFunction(new DottedName([new Name('s')])))->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesSupport(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new SupportFunction(new DottedName([new Name('app'), new Name('s')])))->render($out);
        self::assertSame('SUPPORT app.s', (new Lexical())->join($out->pieces()));
    }
}
