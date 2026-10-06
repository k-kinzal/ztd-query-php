<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\RowConstructor;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\FieldSelection;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Indirection;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Step\Subscript;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\PositionalParameter;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(Indirection::class)]
#[Small]
final class IndirectionTest extends TestCase
{
    public function testOutputNameIsTheLastSelectedField(): void
    {
        self::assertSame('b', (new Indirection(new PositionalParameter('1'), [new FieldSelection(new Name('b')), new Subscript(new NullLiteral())]))->outputName()?->value);
    }

    public function testDeriveScalarSelectsAFieldOfARow(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $row = new Grouped(new RowConstructor([new Constant(new IntegerConstant('1'))]));
        $fact = $derivation->scalar(new Indirection($row, [new FieldSelection(new Name('f1'))]), $derivation->environment());
        self::assertEquals(new Known(Builtin::Int4), $fact->type);
    }

    public function testRenderWritesTheBaseAndTheSteps(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new Indirection(new ColumnReference([new Name('a')]), [new Subscript(new Constant(new IntegerConstant('1'))), new FieldSelection(new Name('b'))]))->render($out);
        self::assertSame('a [1].b', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsAFieldStepRightAfterADottedName(): void
    {
        $this->expectExceptionMessage('A column reference takes steps once a subscript follows its dotted name.');
        new Indirection(new ColumnReference([new Name('a')]), [new FieldSelection(new Name('b'))]);
    }

    public function testRejectsABaseTheGrammarDoesNotIndirect(): void
    {
        $this->expectExceptionMessage('Steps apply to a parenthesized expression, a parameter, a scalar subquery or a column reference.');
        new Indirection(new NullLiteral(), [new FieldSelection(new Name('b'))]);
    }
}
