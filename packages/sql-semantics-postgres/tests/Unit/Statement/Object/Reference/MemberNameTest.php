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
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\MemberName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(MemberName::class)]
#[Small]
final class MemberNameTest extends TestCase
{
    public function testTableAnswersTheOwningRelation(): void
    {
        $dotted = new MemberName(new Name('r'), new DottedName([new Name('s'), new Name('t')]));
        $domain = new MemberName(new Name('c'), new DottedName([new Name('d')]), true);
        $long = new MemberName(new Name('r'), new DottedName([new Name('a'), new Name('b'), new Name('c'), new Name('d')]));
        self::assertSame(['s', null, null], [$dotted->table()?->schema?->value, $domain->table(), $long->table()]);
    }

    public function testDeriveClauseRecordsNothing(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new MemberName(new Name('r'), new QualifiedName(new Name('t'))))->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesTheOwner(): void
    {
        $relation = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new MemberName(new Name('r'), new QualifiedName(new Name('t'), new Name('s'))))->render($relation);
        $domain = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new MemberName(new Name('c'), new DottedName([new Name('d')]), true))->render($domain);
        self::assertSame(['r ON s.t', 'c ON DOMAIN d'], [(new Lexical())->join($relation->pieces()), (new Lexical())->join($domain->pieces())]);
    }
}
