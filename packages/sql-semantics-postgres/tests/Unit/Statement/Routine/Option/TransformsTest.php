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
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\Transforms;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Transforms::class)]
#[Small]
final class TransformsTest extends TestCase
{
    public function testAlterableIsFalse(): void
    {
        self::assertFalse((new Transforms([new TypeName(new NamedDesignation(new DottedName([new Name('int4')])))]))->alterable());
    }

    public function testSettingIsTransform(): void
    {
        self::assertSame('transform', (new Transforms([new TypeName(new NamedDesignation(new DottedName([new Name('int4')])))]))->setting());
    }

    public function testDeriveClauseDerivesTheTypes(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new Transforms([new TypeName(new NamedDesignation(new DottedName([new Name('int4')])))]))->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesForTypeBeforeEachType(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new Transforms([new TypeName(new NamedDesignation(new DottedName([new Name('int4')]))), new TypeName(new NamedDesignation(new DottedName([new Name('hstore')])))]))->render($out);
        self::assertSame('TRANSFORM FOR TYPE int4, FOR TYPE hstore', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsAnEmptyList(): void
    {
        $this->expectExceptionMessage('TRANSFORM names at least one type.');
        new Transforms([]);
    }
}
