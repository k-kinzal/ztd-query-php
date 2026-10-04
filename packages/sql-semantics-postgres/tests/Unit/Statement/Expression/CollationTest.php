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
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Collation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\Negation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\NoCollation;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(Collation::class)]
#[Small]
final class CollationTest extends TestCase
{
    public function testOutputNameIsTheOperandName(): void
    {
        self::assertSame('a', (new Collation(new ColumnReference([new Name('a')]), new DottedName([new Name('C')])))->outputName()?->value);
    }

    public function testDeriveScalarKeepsAStringType(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new Collation(new Constant(new StringConstant('a')), new DottedName([new Name('C')])), $derivation->environment());
        self::assertEquals(new Known(Builtin::Unknown), $fact->type);
    }

    public function testDeriveScalarReportsATypeWithoutCollations(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new Collation(new Constant(new IntegerConstant('1')), new DottedName([new Name('C')])), $derivation->environment());
        self::assertInstanceOf(Invalid::class, $fact->type);
        self::assertInstanceOf(NoCollation::class, $fact->type->cause);
    }

    public function testRenderWritesTheCollationName(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new Collation(new Constant(new StringConstant('a')), new DottedName([new Name('pg_catalog'), new Name('C')])))->render($out);
        self::assertSame("'a' COLLATE pg_catalog.\"C\"", (new Lexical())->join($out->pieces()));
    }

    public function testRejectsAnOperandThatBindsWeaker(): void
    {
        $this->expectExceptionMessage('The operand of COLLATE needs parentheses to keep its place.');
        new Collation(new Negation(new ColumnReference([new Name('a')])), new DottedName([new Name('C')]));
    }
}
