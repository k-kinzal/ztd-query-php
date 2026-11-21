<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Lexical;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Invalid;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\RowConstructor;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\RowSpelling;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\Overlaps;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\RowArity;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

#[CoversClass(Overlaps::class)]
#[Small]
final class OverlapsTest extends TestCase
{
    public function testDeriveScalarIsBooleanAndNullableWithANullField(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new Overlaps(new RowConstructor([new NullLiteral(), new NullLiteral()], RowSpelling::Implicit), new RowConstructor([new NullLiteral(), new NullLiteral()])), $derivation->environment());
        self::assertEquals(new Known(Builtin::Bool), $fact->type);
        self::assertSame(Nullability::Nullable, $fact->nullability);
    }

    public function testDeriveScalarReportsAPeriodOfOneField(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new Overlaps(new RowConstructor([new NullLiteral()]), new RowConstructor([new NullLiteral(), new NullLiteral()])), $derivation->environment());
        self::assertInstanceOf(Invalid::class, $fact->type);
        self::assertInstanceOf(RowArity::class, $fact->type->cause);
    }

    public function testRenderWritesOverlaps(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new Overlaps(new RowConstructor([new NullLiteral(), new NullLiteral()], RowSpelling::Implicit), new RowConstructor([])))->render($out);
        self::assertSame('(NULL, NULL) OVERLAPS ROW ()', (new Lexical())->join($out->pieces()));
    }
}
