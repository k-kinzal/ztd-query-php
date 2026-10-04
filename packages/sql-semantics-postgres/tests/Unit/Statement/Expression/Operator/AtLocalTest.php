<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\AtLocal;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(AtLocal::class)]
#[Small]
final class AtLocalTest extends TestCase
{
    public function testDeriveScalarTurnsATimestampWithZoneIntoATimestamp(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new AtLocal(new Constant(new StringConstant('2024-01-01'))), $derivation->environment());
        self::assertEquals(new Known(Builtin::Timestamp), $fact->type);
    }

    public function testRenderWritesAtLocal(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new AtLocal(new NullLiteral()))->render($out);
        self::assertSame('NULL AT LOCAL', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsAProductAsTheValue(): void
    {
        $product = new BinaryOperation(new OperatorName(new Name('*')), new NullLiteral(), new NullLiteral());
        $this->expectExceptionMessage('The time value needs parentheses to keep its place.');
        new AtLocal($product);
    }
}
