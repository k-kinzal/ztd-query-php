<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\RowExpression;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RowExpression::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class RowExpressionTest extends TestCase
{
    #[TestWith(['SELECT ROW()', 0])]
    #[TestWith(['SELECT ROW(1)', 1])]
    #[TestWith(['SELECT ROW(1, 2)', 2])]
    public function testInputsRetainsPostgreSqlRecordArity(string $sql, int $width): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $statement);
        $row = $statement->outputs[0]->expression;
        self::assertInstanceOf(RowExpression::class, $row);
        self::assertCount($width, $row->items);
        self::assertSame($row->items, $row->inputs());
        self::assertSame($sql, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($sql, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    #[TestWith([Dialect::MySql, 0])]
    #[TestWith([Dialect::MySql, 1])]
    #[TestWith([Dialect::Sqlite, 0])]
    #[TestWith([Dialect::Sqlite, 1])]
    public function testInputsRequiresTwoFieldsOutsidePostgreSql(Dialect $dialect, int $width): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build()))->bind('SELECT (1, 2) = (2, 3)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $row = $statement->outputs[0]->expression->inputs()[0];
        self::assertInstanceOf(RowExpression::class, $row);
        $this->expectException(InvalidStructure::class);
        new RowExpression($row->facts, $row->source, array_fill(0, $width, Expression::literal(1, $dialect)));
    }

    public function testWithFactsKeepsFieldIdentityAndTheOriginalRecord(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT ROW(1, 2)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $row = $statement->outputs[0]->expression;
        self::assertInstanceOf(RowExpression::class, $row);
        $copy = $row->withFacts($row->facts);
        self::assertNotSame($row, $copy);
        self::assertSame($row->items, $copy->items);
        self::assertSame('ROW', $copy->spelling());
    }

    public function testSpellingIsTheRowKeywordEvenWithoutIt(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT (1, 2) = (2, 3)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $row = $statement->outputs[0]->expression->inputs()[0];
        self::assertInstanceOf(RowExpression::class, $row);
        self::assertSame('ROW', $row->spelling());
        self::assertSame('SELECT ((1, 2) = (2, 3))', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
