<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Intrinsic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Intrinsic\FieldSpelling;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Temporal\Extract;
use SqlSemantics\Model\Scalar\Temporal\PostgreSqlField;
use SqlSemantics\SchemaBuilder;

#[CoversClass(FieldSpelling::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class FieldSpellingTest extends TestCase
{
    #[TestWith(["'year'"])]
    #[TestWith(["E'y\\x65ar'"])]
    #[TestWith(["E'y\\145ar'"])]
    #[TestWith(["E'y\\u0065ar'"])]
    #[TestWith(["E'y\\U00000065ar'"])]
    #[TestWith(["U&'y!0065ar' UESCAPE '!'"])]
    #[TestWith(["U&'y\\0065ar'"])]
    #[TestWith(['U&"y!0065ar" UESCAPE \'!\''])]
    #[TestWith(['$field$year$field$'])]
    #[TestWith(["'ye'\n'ar'"])]
    #[TestWith(["E'ye' -- 'ignored'\n'ar'"])]
    #[TestWith(['"YEAR"'])]
    public function testReadPreservesTheMeaningOfQuotedFieldNames(string $field): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT EXTRACT(' . $field . ' FROM CURRENT_TIMESTAMP)');
        self::assertInstanceOf(BoundSelect::class, $query);
        $extract = $query->outputs[0]->expression;
        self::assertInstanceOf(Extract::class, $extract);
        self::assertSame(PostgreSqlField::Year, $extract->field);
        self::assertSame('SELECT EXTRACT(YEAR FROM CURRENT_TIMESTAMP)', (new \SqlSemantics\SimpleSerializer())->serialize($query));
    }

    public function testQuotedJoinsContinuationPieces(): void
    {
        self::assertSame('year', FieldSpelling::quoted("'ye'\n'ar'", false));
    }

    #[TestWith(['IDENT', 'Foo', 'foo'])]
    #[TestWith(['UIDENT', 'u&"d\\0061t"', 'dat'])]
    #[TestWith(['SCONST', "e'a\\tb'", "a\tb"])]
    #[TestWith(['SCONST', "e'a\\'b'", "a'b"])]
    #[TestWith(['SCONST', "\$\$a\nb\$\$", "a\nb"])]
    #[TestWith(['SCONST', "U&'d!0061t' uescape '!'", 'dat'])]
    #[TestWith(['SCONST', "U&'\\+000061\\\\'", 'a\\'])]
    #[TestWith(['SCONST', "'it''s'", "it's"])]
    #[TestWith(['SCONST', "'a' 'b' 'c'", 'abc'])]
    public function testReadDecodesEachSpellingDirectly(string $name, string $text, string $expected): void
    {
        self::assertSame($expected, FieldSpelling::read(new Token(0, $name, $text, 0), new Identifiers(Dialect::PostgreSql)));
    }

    public function testQuotedUndoublesQuotesOfEveryPiece(): void
    {
        self::assertSame('"a"b', FieldSpelling::quoted('"""a""" "b"', false, '"'));
    }
}
