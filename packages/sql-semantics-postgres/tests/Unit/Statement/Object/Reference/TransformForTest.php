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
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\TransformFor;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(TransformFor::class)]
#[Small]
final class TransformForTest extends TestCase
{
    public function testDeriveClauseDerivesTheType(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new TransformFor(new TypeName(new NamedDesignation(new DottedName([new Name('int4')]))), new Name('plperl')))->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesTypeAndLanguage(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new TransformFor(new TypeName(new NamedDesignation(new DottedName([new Name('int4')]))), new Name('plperl')))->render($out);
        self::assertSame('FOR int4 LANGUAGE plperl', (new Lexical())->join($out->pieces()));
    }
}
