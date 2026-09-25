<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\DropIndexConcurrentlyStatement;
use SqlSemantics\Model\Statement\Definition\DropTableIndexStatement;
use SqlSemantics\Model\Statement\Definition\DropTableTriggerStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\OwnedDrops;

#[CoversClass(OwnedDrops::class)]
#[Medium]
final class OwnedDropsTest extends TestCase
{
    /**
     * @param class-string<DropIndexConcurrentlyStatement|DropTableIndexStatement|DropTableTriggerStatement> $class
     */
    #[TestWith([Dialect::MySql, 'DROP INDEX ix ON t ALGORITHM=INPLACE LOCK=NONE', 'DROP INDEX `ix` ON `t` ALGORITHM = INPLACE LOCK = NONE', DropTableIndexStatement::class])]
    #[TestWith([Dialect::MySql, 'DROP INDEX ix ON app.t', 'DROP INDEX `ix` ON `app`.`t` ALGORITHM = DEFAULT LOCK = DEFAULT', DropTableIndexStatement::class])]
    #[TestWith([Dialect::PostgreSql, 'DROP INDEX CONCURRENTLY IF EXISTS ix', 'DROP INDEX CONCURRENTLY IF EXISTS "ix"', DropIndexConcurrentlyStatement::class])]
    #[TestWith([Dialect::PostgreSql, 'DROP INDEX CONCURRENTLY app.ix', 'DROP INDEX CONCURRENTLY "app"."ix"', DropIndexConcurrentlyStatement::class])]
    #[TestWith([Dialect::PostgreSql, 'DROP TRIGGER IF EXISTS tr ON public.t CASCADE', 'DROP TRIGGER IF EXISTS "tr" ON "public"."t" CASCADE', DropTableTriggerStatement::class])]
    #[TestWith([Dialect::PostgreSql, 'DROP TRIGGER tr ON t', 'DROP TRIGGER "tr" ON "t"', DropTableTriggerStatement::class])]
    public function testWriteSerializesOwnershipAndConcurrencyFromTheDropForm(Dialect $dialect, string $sql, string $expected, string $class): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build());
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, OwnedDrops::write($statement)->toString());
        $rebound = $binder->bind($expected, strict: false);
        self::assertInstanceOf($class, $rebound);
        self::assertSame($expected, $rebound->toString());
    }

    public function testWriteKeepsTheAlgorithmAndLockOfAReboundMysqlDrop(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('DROP INDEX ix ON t ALGORITHM=INPLACE LOCK=NONE', strict: false);
        self::assertInstanceOf(DropTableIndexStatement::class, $statement);
        $rebound = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement), strict: false);
        self::assertInstanceOf(DropTableIndexStatement::class, $rebound);
        self::assertSame($statement->algorithm, $rebound->algorithm);
        self::assertSame($statement->lock, $rebound->lock);
        self::assertSame(['t'], $rebound->table->parts);
    }
}
