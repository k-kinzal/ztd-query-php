<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Constructor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\ArrayConstructor;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\ArrayItems;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\TypeConflict;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\ArrayOf;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(ArrayConstructor::class)]
#[Small]
final class ArrayConstructorTest extends TestCase
{
    public function testOutputNameIsArray(): void
    {
        self::assertSame('array', (new ArrayConstructor(new ArrayItems()))->outputName()->value);
    }

    public function testDeriveScalarIsAnArrayOfTheCommonType(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $items = new ArrayItems([], [new ArrayItems([new Constant(new StringConstant('1'))]), new ArrayItems([new Constant(new IntegerConstant('2'))])]);
        $fact = $derivation->scalar(new ArrayConstructor($items), $derivation->environment());
        self::assertEquals(new Known(new ArrayOf(Builtin::Int4)), $fact->type);
        self::assertSame(Nullability::NotNull, $fact->nullability);
    }

    public function testDeriveScalarReportsValuesWithoutACommonType(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new ArrayConstructor(new ArrayItems([new Constant(new IntegerConstant('1')), new BooleanLiteral(true)])), $derivation->environment());
        self::assertInstanceOf(Invalid::class, $fact->type);
        self::assertInstanceOf(TypeConflict::class, $fact->type->cause);
    }

    public function testRenderWritesArray(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new ArrayConstructor(new ArrayItems()))->render($out);
        self::assertSame('ARRAY []', (new Lexical())->join($out->pieces()));
    }
}
