<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Scalar\Query\RowSubquery;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RowSubquery::class)]
final class RowSubqueryTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testComparisonKeepsTheWholeResultRow(Dialect $dialect): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build());
        $query = $binder->bind('SELECT (1,2)=(SELECT 1,2)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $value = $query->outputs[0]->expression->right;
        self::assertInstanceOf(RowSubquery::class, $value);
        self::assertCount(2, $value->query->resultColumns());
        self::assertSame('record', $value->type->name);
        self::assertInstanceOf(RowSubquery::class, $binder->bind($query->toString())->outputs[0]->expression->right);
    }

    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testDoesNotTreatMultipleColumnsAsAScalar(Dialect $dialect): void
    {
        $this->expectException(InvalidSql::class);
        (new Binder((new SchemaBuilder($dialect))->build()))->bind('SELECT (SELECT 1,2)', strict: false);
    }

    public function testComparisonRequiresTheSameNumberOfFields(): void
    {
        $this->expectException(InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT (1,2)=(SELECT 1,2,3)', strict: false);
    }

    public function testACompositeValueRemainsOneQueryOutput(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind('SELECT (SELECT ROW(1,2))=ROW(1,2)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Query\ScalarSubquery::class, $query->outputs[0]->expression->left);
        self::assertCount(1, $query->outputs[0]->expression->left->query->resultColumns());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }
}
