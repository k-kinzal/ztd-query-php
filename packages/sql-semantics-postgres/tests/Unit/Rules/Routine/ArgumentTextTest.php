<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\ArgumentText;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NumericConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Option\KeywordWord;
use SqlSemantics\Platform\PostgreSql\Statement\Type\ArrayBound;
use SqlSemantics\Platform\PostgreSql\Statement\Type\ArraySpecifier;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\ColumnDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\KeywordDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\TypeKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ArgumentText::class)]
#[Small]
final class ArgumentTextTest extends TestCase
{
    public function testTextReadsEachValueKind(): void
    {
        $text = new ArgumentText();
        self::assertSame(
            ['pg_catalog.int4[]', 't.a%TYPE', 'select', 's.+', '-7', 'X'],
            [
                $text->text(new TypeName(new KeywordDesignation(TypeKeyword::Integer), false, new ArraySpecifier([new ArrayBound()]))),
                $text->text(new TypeName(new ColumnDesignation(new DottedName([new Name('t'), new Name('a')])))),
                $text->text(new KeywordWord(new Name('select'))),
                $text->text(new OperatorName(new Name('+'), [new Name('s')])),
                $text->text(new SignedNumber(true, new IntegerConstant('7'))),
                $text->text(new StringConstant('X')),
            ],
        );
    }

    public function testNamesAddsTheCatalogSchemaToATypeKeyword(): void
    {
        self::assertSame(['pg_catalog', 'int4'], array_map(static fn (Name $name): string => $name->value, (new ArgumentText())->names(new TypeName(new KeywordDesignation(TypeKeyword::Integer)))));
    }

    public function testIntegerAnswersOnlyThirtyTwoBitIntegers(): void
    {
        $text = new ArgumentText();
        self::assertSame([2147483647, null, null], [$text->integer(new SignedNumber(false, new IntegerConstant('2147483647'))), $text->integer(new SignedNumber(true, new NumericConstant('2147483648'))), $text->integer(new SignedNumber(false, new NumericConstant('1.5')))]);
    }

    public function testNumberWritesTheTextOfAFloatAsWritten(): void
    {
        $text = new ArgumentText();
        self::assertSame(['-1.50E3', '9_999_999_999', '-7'], [$text->number(new SignedNumber(true, new NumericConstant('1.50E3'))), $text->number(new SignedNumber(false, new NumericConstant('9_999_999_999'))), $text->number(new SignedNumber(true, new IntegerConstant('7')))]);
    }

    public function testJoinedSeparatesNamesWithDots(): void
    {
        self::assertSame('a.b', (new ArgumentText())->joined([new Name('a'), new Name('b')]));
    }
}
