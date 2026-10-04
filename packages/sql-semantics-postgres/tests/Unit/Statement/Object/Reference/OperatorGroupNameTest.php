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
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\OperatorGroupName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(OperatorGroupName::class)]
#[Small]
final class OperatorGroupNameTest extends TestCase
{
    public function testDeriveClauseRecordsNothing(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new OperatorGroupName(new DottedName([new Name('c')]), new Name('btree')))->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesTheAccessMethod(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new OperatorGroupName(new DottedName([new Name('s'), new Name('c')]), new Name('gist')))->render($out);
        self::assertSame('s.c USING gist', (new Lexical())->join($out->pieces()));
    }
}
