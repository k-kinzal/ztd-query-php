<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Lexical;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text\Overlay;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

#[CoversClass(Overlay::class)]
#[Small]
final class OverlayTest extends TestCase
{
    public function testOutputNameIsOverlay(): void
    {
        self::assertSame('overlay', (new Overlay(new NullLiteral(), new NullLiteral(), new NullLiteral()))->outputName()->value);
    }

    public function testDeriveScalarIsTheStringType(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new Overlay(new Constant(new StringConstant('abc')), new Constant(new StringConstant('x')), new Constant(new IntegerConstant('1')), new Constant(new IntegerConstant('1'))), $derivation->environment());
        self::assertEquals(new ScalarFact(new Known(Builtin::Text), Nullability::NotNull), $fact);
    }

    public function testRenderWritesTheKeywords(): void
    {
        $overlay = new Overlay(new Constant(new StringConstant('abc')), new Constant(new StringConstant('x')), new Constant(new IntegerConstant('1')), new Constant(new IntegerConstant('1')));
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $overlay->render($out);
        self::assertSame('OVERLAY(\'abc\' PLACING \'x\' FROM 1 FOR 1)', (new Lexical())->join($out->pieces()));
    }
}
