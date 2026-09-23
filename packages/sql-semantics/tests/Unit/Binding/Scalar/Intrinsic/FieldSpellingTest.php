<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Intrinsic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
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
        self::assertSame('SELECT EXTRACT(YEAR FROM CURRENT_TIMESTAMP)', $query->toString());
    }

    public function testQuotedJoinsContinuationPieces(): void
    {
        self::assertSame('year', FieldSpelling::quoted("'ye'\n'ar'", false));
    }
}
