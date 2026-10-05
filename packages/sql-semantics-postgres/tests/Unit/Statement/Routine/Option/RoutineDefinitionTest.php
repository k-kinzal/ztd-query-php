<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\RoutineDefinition;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\RoutineSource;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(RoutineDefinition::class)]
#[Small]
final class RoutineDefinitionTest extends TestCase
{
    public function testAlterableIsFalse(): void
    {
        self::assertFalse((new RoutineDefinition(new RoutineSource(new Name('sql'), new StringConstant('x'))))->alterable());
    }

    public function testSettingIsAs(): void
    {
        self::assertSame('as', (new RoutineDefinition(new RoutineSource(new Name('sql'), new StringConstant('x'))))->setting());
    }

    public function testDeriveClauseRecordsNothing(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new RoutineDefinition(new RoutineSource(new Name('sql'), new StringConstant('x'))))->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesFileAndSymbol(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new RoutineDefinition(new RoutineSource(new Name('c'), new StringConstant('lib')), new StringConstant("it's")))->render($out);
        self::assertSame("AS 'lib', 'it''s'", (new Lexical())->join($out->pieces()));
    }
}
