<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Validation\QueryComparison;
use SqlSemantics\SchemaBuilder;

#[CoversClass(QueryComparison::class)]
#[Medium]
final class QueryComparisonTest extends TestCase
{
    public function testWidthCountsRowItemsAndScalarOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('SELECT (1, 2), (SELECT 1), 1, ROW(1, 2, 3) FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertSame([2, 1, 1, 3], array_map(static fn ($output): ?int => QueryComparison::width($output->expression), $statement->outputs));
    }

    public function testWidthLeavesAnUnresolvedOperandUnknown(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT missing', strict: false);
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertNull(QueryComparison::width($statement->outputs[0]->expression));
    }

    public function testOperandsRequireEqualKnownWidths(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT (1, 2), (SELECT 1), 1, missing', strict: false);
        self::assertInstanceOf(BoundSelect::class, $statement);
        [$row, $subquery, $scalar, $unknown] = array_map(static fn ($output) => $output->expression, $statement->outputs);
        self::assertFalse(QueryComparison::operands($row, $subquery));
        self::assertTrue(QueryComparison::operands($scalar, $subquery));
        self::assertTrue(QueryComparison::operands($unknown, $row));
    }

    public function testCompatibleMatchesAValueAgainstTheQueryWidth(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('SELECT 1, (1, 2)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $scalar = $statement->outputs[0]->expression;
        $row = $statement->outputs[1]->expression;
        $one = $binder->bind('SELECT 1');
        $two = $binder->bind('SELECT 1, 2');
        $open = $binder->bind('SELECT * FROM missing', strict: false);
        self::assertInstanceOf(BoundSelect::class, $one);
        self::assertInstanceOf(BoundSelect::class, $two);
        self::assertInstanceOf(BoundSelect::class, $open);
        self::assertTrue(QueryComparison::compatible($scalar, $one));
        self::assertFalse(QueryComparison::compatible($scalar, $two));
        self::assertTrue(QueryComparison::compatible($row, $two));
        self::assertTrue(QueryComparison::compatible($scalar, $open));
    }
}
