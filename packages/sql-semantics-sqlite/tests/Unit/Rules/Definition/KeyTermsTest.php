<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Rules\Definition\KeyTerms;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Collate;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\DoubleQuotedWord;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Schema\LiteralColumn;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(KeyTerms::class)]
#[Small]
final class KeyTermsTest extends TestCase
{
    public function testCoreLooksThroughParenthesesAndCollations(): void
    {
        $column = new ColumnUse(new Name('a'));

        self::assertSame($column, (new KeyTerms())->core(new Grouped(new Collate($column, new Name('nocase')))));
        self::assertSame($column, (new KeyTerms())->core($column));
    }

    public function testColumnReadsAPlainNameAStringAndADoubleQuotedWord(): void
    {
        $terms = new KeyTerms();

        self::assertSame('a', $terms->column(new ColumnUse(new Name('a')))?->value);
        self::assertSame('a', $terms->column(new Grouped(new ColumnUse(new Name('a'))))?->value);
        self::assertSame('b', $terms->column(new LiteralColumn(new TextLiteral('b')))?->value);
        self::assertNull($terms->column(new TextLiteral('b')));
        self::assertSame('c', $terms->column(new DoubleQuotedWord(new Name('c')))?->value);
    }

    public function testColumnIsNullForAQualifiedReferenceAndForAnExpression(): void
    {
        $terms = new KeyTerms();

        self::assertNull($terms->column(new ColumnUse(new Name('a'), new QualifiedName(new Name('t')))));
        self::assertNull($terms->column(new IntegerLiteral('1')));
    }

    public function testReferenceAcceptsEveryColumnReferenceAndNoOtherExpression(): void
    {
        $terms = new KeyTerms();

        self::assertTrue($terms->reference(new ColumnUse(new Name('a'), new QualifiedName(new Name('t')))));
        self::assertTrue($terms->reference(new Collate(new ColumnUse(new Name('a')), new Name('nocase'))));
        self::assertTrue($terms->reference(new LiteralColumn(new TextLiteral('a'))));
        self::assertFalse($terms->reference(new TextLiteral('a')));
        self::assertFalse($terms->reference(new IntegerLiteral('1')));
    }

    public function testBeneathStopsAtTheCollationLimit(): void
    {
        $terms = new KeyTerms();
        $literal = new TextLiteral('a');
        $once = new Collate(new Grouped($literal), new Name('nocase'));
        $twice = new Collate($once, new Name('binary'));

        self::assertSame($literal, $terms->beneath(new Grouped($twice), null));
        self::assertSame($literal, $terms->beneath(new Grouped($once), 1));
        self::assertSame($once, $terms->beneath(new Grouped($twice), 1));
        self::assertSame($once, $terms->beneath($once, 0));
    }

    public function testStringTellsALiteralUnderTheAdmittedWrappers(): void
    {
        $terms = new KeyTerms();
        $once = new Collate(new Grouped(new TextLiteral('a')), new Name('nocase'));
        $twice = new Collate($once, new Name('binary'));

        self::assertTrue($terms->string($twice, null));
        self::assertTrue($terms->string($once, 1));
        self::assertFalse($terms->string($twice, 1));
        self::assertFalse($terms->string(new Grouped(new ColumnUse(new Name('a'))), null));
    }
}
