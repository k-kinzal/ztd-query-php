<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Designation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\KeywordDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\TypeKeyword;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(KeywordDesignation::class)]
#[Small]
final class KeywordDesignationTest extends TestCase
{
    public function testTypeFactIsTheCatalogTypeWhateverThePath(): void
    {
        $shadowed = (new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), ['app'], [], false);
        self::assertEquals(new Known(Builtin::Int4), (new KeywordDesignation(TypeKeyword::Int))->typeFact($shadowed, false));
    }

    public function testCatalogNameIsTheNameInTheCatalog(): void
    {
        self::assertSame('float8', (new KeywordDesignation(TypeKeyword::DoublePrecision))->catalogName()->value);
    }

    public function testDeriveClauseRecordsNothing(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new KeywordDesignation(TypeKeyword::Boolean))->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesTheKeywords(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new KeywordDesignation(TypeKeyword::DoublePrecision))->render($out);
        self::assertSame('DOUBLE PRECISION', (new Lexical())->join($out->pieces()));
    }
}
