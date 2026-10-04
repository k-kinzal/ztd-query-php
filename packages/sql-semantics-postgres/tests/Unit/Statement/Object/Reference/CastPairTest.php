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
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\CastPair;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(CastPair::class)]
#[Small]
final class CastPairTest extends TestCase
{
    public function testDeriveClauseDerivesBothTypes(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new CastPair(new TypeName(new NamedDesignation(new DottedName([new Name('int4')]))), new TypeName(new NamedDesignation(new DottedName([new Name('text')])))))->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesTheParenthesizedPair(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new CastPair(new TypeName(new NamedDesignation(new DottedName([new Name('int4')]))), new TypeName(new NamedDesignation(new DottedName([new Name('text')])))))->render($out);
        self::assertSame('(int4 AS text)', (new Lexical())->join($out->pieces()));
    }
}
