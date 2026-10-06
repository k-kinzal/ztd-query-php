<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Name;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(OperatorName::class)]
#[Small]
final class OperatorNameTest extends TestCase
{
    public function testDeriveClauseRecordsNothing(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new OperatorName(new Name('+')))->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesABareOperator(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new OperatorName(new Name('@>')))->render($out);
        self::assertSame('@>', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesTheOperatorSyntaxWithQualifiers(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new OperatorName(new Name('`'), [new Name('pg_catalog')], true))->render($out);
        self::assertSame('OPERATOR (pg_catalog.`)', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesBareQualifiersForAnObjectName(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new OperatorName(new Name('+'), [new Name('App')]))->render($out);
        self::assertSame('"App".+', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsTextThatIsNotAnOperator(): void
    {
        $this->expectExceptionMessage('An operator name is 1 to 63 operator characters.');
        new OperatorName(new Name('a'));
    }

    public function testRejectsTheAliasOfNotEquals(): void
    {
        $this->expectExceptionMessage('An operator name holds no comment start');
        new OperatorName(new Name('!='));
    }

    public function testRejectsATrailingSignWithoutASpecialCharacter(): void
    {
        $this->expectExceptionMessage('A multi-character operator ends in + or - only when');
        new OperatorName(new Name('*-'));
    }
}
