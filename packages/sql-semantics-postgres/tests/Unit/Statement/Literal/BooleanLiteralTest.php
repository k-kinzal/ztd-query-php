<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Lexical;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

#[CoversClass(BooleanLiteral::class)]
#[Medium]
final class BooleanLiteralTest extends TestCase
{
    public function testGivesAnUnaliasedColumnNoName(): void
    {
        $query = (new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('SELECT true, false');
        self::assertSame(['?column?', '?column?'], [$query->field(0)->name?->value, $query->field(1)->name?->value]);
    }

    public function testADerivedColumnOfAConstantIsNotNamedBool(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $missing = $semantics->diagnose('SELECT bool FROM (SELECT true) s');
        $found = $semantics->diagnose('SELECT "?column?" FROM (SELECT true) s');
        self::assertSame(['Column bool does not exist.'], array_map(static fn (\SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), $missing->facts->diagnostics));
        self::assertSame([], $found->facts->diagnostics);
        self::assertEquals(new Known(Builtin::Bool), $found->field(0)->type);
    }

    public function testDeriveScalarIsBooleanAndNeverNull(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new BooleanLiteral(false), $derivation->environment());
        self::assertEquals(new Known(Builtin::Bool), $fact->type);
        self::assertSame(Nullability::NotNull, $fact->nullability);
    }

    public function testRenderWritesTheKeyword(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new BooleanLiteral(false))->render($out);
        self::assertSame('FALSE', (new Lexical())->join($out->pieces()));
    }
}
