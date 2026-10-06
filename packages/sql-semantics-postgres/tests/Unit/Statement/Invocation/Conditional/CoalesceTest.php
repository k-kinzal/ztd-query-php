<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Conditional;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Conditional\Coalesce;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Coalesce::class)]
#[Small]
final class CoalesceTest extends TestCase
{
    public function testRejectsNoValue(): void
    {
        $this->expectExceptionMessage('COALESCE takes at least one value.');
        new Coalesce([]);
    }

    public function testOutputNameIsCoalesce(): void
    {
        self::assertSame('coalesce', (new Coalesce([new NullLiteral()]))->outputName()->value);
    }

    public function testDeriveScalarIsNotNullWhenAValueIsNotNull(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new Coalesce([new NullLiteral(), new Constant(new IntegerConstant('1'))]), $derivation->environment());
        self::assertEquals(new ScalarFact(new Known(Builtin::Int4), Nullability::NotNull), $fact);
    }

    public function testRenderWritesTheValues(): void
    {
        $coalesce = new Coalesce([new NullLiteral(), new Constant(new IntegerConstant('1'))]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $coalesce->render($out);
        self::assertSame('COALESCE(NULL, 1)', (new Lexical())->join($out->pieces()));
    }
}
