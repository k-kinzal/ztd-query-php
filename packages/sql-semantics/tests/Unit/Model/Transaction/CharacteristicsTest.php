<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Transaction\BeginTransactionStatement;
use SqlSemantics\Model\Transaction\Access;
use SqlSemantics\Model\Transaction\Characteristics;
use SqlSemantics\Model\Transaction\Isolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Characteristics::class)]
#[Medium]
final class CharacteristicsTest extends TestCase
{
    public function testOmittedSettingsReferToTheCurrentEnvironment(): void
    {
        $characteristics = new Characteristics();
        self::assertNull($characteristics->isolation);
        self::assertNull($characteristics->access);
        self::assertNull($characteristics->deferrable);
        self::assertFalse($characteristics->consistentSnapshot);
    }

    public function testRetainsEveryPostgreSqlTransactionMode(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('START TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ WRITE, NOT DEFERRABLE');
        self::assertInstanceOf(BeginTransactionStatement::class, $statement);
        self::assertSame(Isolation::RepeatableRead, $statement->characteristics->isolation);
        self::assertSame(Access::ReadWrite, $statement->characteristics->access);
        self::assertFalse($statement->characteristics->deferrable);
        self::assertFalse($statement->characteristics->consistentSnapshot);
        self::assertSame('BEGIN ISOLATION LEVEL REPEATABLE READ, READ WRITE, NOT DEFERRABLE', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testRetainsTheMySqlSnapshotRequest(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
        self::assertInstanceOf(BeginTransactionStatement::class, $statement);
        self::assertTrue($statement->characteristics->consistentSnapshot);
        self::assertSame(Access::ReadOnly, $statement->characteristics->access);
        self::assertNull($statement->characteristics->isolation);
        self::assertSame('START TRANSACTION READ ONLY, WITH CONSISTENT SNAPSHOT', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }
}
