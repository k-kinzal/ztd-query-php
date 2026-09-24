<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Intrinsic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Intrinsic\IntrinsicBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(IntrinsicBinder::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class IntrinsicBinderTest extends TestCase
{
    public function testBindRetainsTheIntrinsicOperands(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT EXTRACT(YEAR FROM CURRENT_TIMESTAMP)');
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Temporal\Extract::class, $query->outputs[0]->expression);
    }

    public function testBindLeavesOrdinaryFunctionsToSignatureResolution(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT "position"(1, 2), "extract"(1)');
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\FunctionCall::class, $query->outputs[0]->expression);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\FunctionCall::class, $query->outputs[1]->expression);
    }

    public function testBindReadsALowerCaseSqliteRaise(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INT)')))->bind("CREATE TRIGGER tr AFTER INSERT ON t BEGIN SELECT raise(abort, 'no'); END");
        self::assertSame("CREATE TRIGGER \"tr\" AFTER INSERT ON \"main\".\"t\" FOR EACH ROW BEGIN SELECT RAISE(ABORT, 'no'); END", $statement->toString());
    }

    public function testBindLeavesRaiseAsAFunctionOutsideSqlite(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT raise(1)', strict: false);
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\FunctionCall::class, $query->outputs[0]->expression);
    }

    public function testBindReadsAContextValue(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT CURRENT_USER');
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\ContextReference::class, $query->outputs[0]->expression);
    }


    public function testTemporalResolvesTimestampArithmetic(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(d DATETIME)')))->bind('SELECT TIMESTAMPADD(HOUR, 2, d), EXTRACT(YEAR FROM d) FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Temporal\TimestampAdd::class, $query->outputs[0]->expression);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Temporal\Extract::class, $query->outputs[1]->expression);
    }
}
