<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\NameArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;
use SqlSemantics\Platform\PostgreSql\Statement\Option\KeywordWord;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\KeywordDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\TypeKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(NameArgument::class)]
#[Small]
final class NameArgumentTest extends TestCase
{
    public function testPartsAnswersTheNamesTheServerReads(): void
    {
        self::assertSame(['s', 'f'], array_map(static fn (Name $part): string => $part->value, (new NameArgument(ObjectKind::Function, new DottedName([new Name('s'), new Name('f')])))->parts()));
        self::assertSame(['pg_catalog', 'int4'], array_map(static fn (Name $part): string => $part->value, (new NameArgument(ObjectKind::Function, new TypeName(new KeywordDesignation(TypeKeyword::Integer))))->parts()));
        self::assertSame(['s', '+'], array_map(static fn (Name $part): string => $part->value, (new NameArgument(ObjectKind::Operator, new OperatorName(new Name('+'), [new Name('s')])))->parts()));
        self::assertSame(['F'], array_map(static fn (Name $part): string => $part->value, (new NameArgument(ObjectKind::Function, new StringConstant('F')))->parts()));
        self::assertSame(['none'], array_map(static fn (Name $part): string => $part->value, (new NameArgument(ObjectKind::Operator, new KeywordWord(new Name('none'))))->parts()));
    }

    public function testFitsAReadingOfTheSameKind(): void
    {
        $name = new NameArgument(ObjectKind::Function, new DottedName([new Name('f')]));
        self::assertSame([true, false], [$name->fits(Reading::Function), $name->fits(Reading::Operator)]);
    }

    public function testDeriveClauseDerivesTheModifiersOfATypeName(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $modifier = new Constant(new IntegerConstant('1'));
        (new NameArgument(ObjectKind::Function, new TypeName(new NamedDesignation(new DottedName([new Name('f')]), [$modifier]))))->deriveClause($derivation, $derivation->environment());
        self::assertTrue($derivation->facts()->covers($modifier));
    }

    public function testRenderWritesADottedNameWhereATypeNameIsRead(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new NameArgument(ObjectKind::Function, new DottedName([new Name('S'), new Name('f')])))->render($out);
        self::assertSame('"S".f', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesOtherSpellingsAsWritten(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new NameArgument(ObjectKind::Function, new StringConstant('f')))->render($out);
        self::assertSame("'f'", (new Lexical())->join($out->pieces()));
    }

    public function testRejectsAPlainTypeName(): void
    {
        $this->expectExceptionMessage('A plain name is written as a dotted name, not as a type name.');
        new NameArgument(ObjectKind::Function, new TypeName(new NamedDesignation(new DottedName([new Name('f')]))));
    }

    public function testRejectsAKindNoAttributeNames(): void
    {
        $this->expectExceptionMessage('A definition attribute names a function, an operator, an operator class, a collation, a text search object or a type.');
        new NameArgument(ObjectKind::Table, new DottedName([new Name('t')]));
    }
}
