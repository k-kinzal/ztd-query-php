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
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\Composite;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\RowConstructor;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\RowSpelling;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(RowConstructor::class)]
#[Small]
final class RowConstructorTest extends TestCase
{
    public function testOutputNameIsRow(): void
    {
        self::assertSame('row', (new RowConstructor([]))->outputName()->value);
    }

    public function testDeriveScalarIsARecordOfTheFields(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new RowConstructor([new Constant(new IntegerConstant('1')), new Constant(new StringConstant('a'))]), $derivation->environment());
        self::assertEquals(new Known(new Composite([new OutputSlot(new Name('f1'), new Known(Builtin::Int4), Nullability::NotNull), new OutputSlot(new Name('f2'), new Known(Builtin::Text), Nullability::NotNull)])), $fact->type);
        self::assertSame(Nullability::NotNull, $fact->nullability);
    }

    public function testRenderWritesBothSpellings(): void
    {
        $explicit = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new RowConstructor([new NullLiteral()]))->render($explicit);
        $implicit = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new RowConstructor([new NullLiteral(), new NullLiteral()], RowSpelling::Implicit))->render($implicit);
        self::assertSame(['ROW (NULL)', '(NULL, NULL)'], [(new Lexical())->join($explicit->pieces()), (new Lexical())->join($implicit->pieces())]);
    }

    public function testRejectsAnImplicitRowOfOneField(): void
    {
        $this->expectExceptionMessage('A row without ROW has at least two fields.');
        new RowConstructor([new NullLiteral()], RowSpelling::Implicit);
    }
}
