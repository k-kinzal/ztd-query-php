<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Replication\Subscription as Operand;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Serialization\Definition\Replication\Subscriptions::class)]
#[Medium]
final class SubscriptionsTest extends TestCase
{
    #[TestWith(['CREATE SUBSCRIPTION "s" CONNECTION \'host=a\' PUBLICATION "p", "Q" WITH (connect = false, slot_name = NONE, streaming = \'parallel\')'])]
    #[TestWith(['ALTER SUBSCRIPTION "s" SET (slot_name = \'x\', synchronous_commit = \'local\', origin = \'none\')'])]
    #[TestWith(['ALTER SUBSCRIPTION "s" SET PUBLICATION "p" WITH (copy_data = false, refresh = true)'])]
    #[TestWith(['ALTER SUBSCRIPTION "s" SKIP(lsn = \'0/1\')'])]
    #[TestWith(['DROP SUBSCRIPTION "s" CASCADE'])]
    public function testWriteIsAFixedPoint(string $sql): void
    {
        self::assertSame($sql, (new \SqlSemantics\SimpleSerializer())->serialize((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql)));
    }

    public function testWriteReturnsNullForOtherStatements(): void
    {
        self::assertNull(\SqlSemantics\Serialization\Definition\Replication\Subscriptions::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')));
    }

    public function testAlterNamesTheSubscription(): void
    {
        self::assertSame('ALTER SUBSCRIPTION "s" ENABLE', \SqlSemantics\Serialization\Definition\Replication\Subscriptions::alter('s', [\SqlSemantics\Model\Sql\Build::keyword('ENABLE')])->toString());
    }

    public function testNamesQuotesEachName(): void
    {
        self::assertSame('"a", "B"', \SqlSemantics\Serialization\Definition\Replication\Subscriptions::names(['a', 'B'])->toString());
    }

    public function testTextWritesAStringConstant(): void
    {
        self::assertSame("'it''s'", \SqlSemantics\Serialization\Definition\Replication\Subscriptions::text("it's")->toString());
    }

    public function testOptionsIsEmptyWithoutOptions(): void
    {
        self::assertSame([], \SqlSemantics\Serialization\Definition\Replication\Subscriptions::options(new Operand\SubscriptionOptions(), true));
    }
}
