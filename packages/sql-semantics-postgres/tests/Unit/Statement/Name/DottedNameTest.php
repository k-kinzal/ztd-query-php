<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Name;

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

#[CoversClass(DottedName::class)]
#[Small]
final class DottedNameTest extends TestCase
{
    public function testLastAnswersTheObjectName(): void
    {
        self::assertSame('c', (new DottedName([new Name('a'), new Name('b'), new Name('c')]))->last()->value);
    }

    public function testQualifiedReadsUpToThreeParts(): void
    {
        $name = (new DottedName([new Name('c'), new Name('s'), new Name('t')]))->qualified();
        self::assertSame(['c', 's', 't'], [$name?->catalog?->value, $name?->schema?->value, $name?->name->value]);
        self::assertNull((new DottedName([new Name('t')]))->qualified()?->schema);
        self::assertNull((new DottedName([new Name('a'), new Name('b'), new Name('c'), new Name('d')]))->qualified());
    }

    public function testDeriveClauseRecordsNothing(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new DottedName([new Name('t')]))->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderQuotesOnlyThePartsThatNeedIt(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new DottedName([new Name('App'), new Name('select'), new Name('x y')]))->render($out);
        self::assertSame('"App".select."x y"', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsANameWithoutParts(): void
    {
        $this->expectExceptionMessage('A dotted name has at least one part.');
        new DottedName([]);
    }
}
