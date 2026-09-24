<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\DropBinder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DropBinder::class)]
#[Medium]
final class DropBinderTest extends TestCase
{
    public function testMysqlIndexRequiresTheTableAndReadsRebuildPolicies(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT, KEY ix(a))'));
        $statement = $binder->bind('DROP INDEX ix ON t ALGORITHM=INPLACE LOCK=NONE');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\DropTableIndexStatement::class, $statement);
        self::assertSame('ix', $statement->name);
        self::assertSame(['t'], $statement->table->parts);
        self::assertSame(\SqlSemantics\Model\Definition\IndexAlgorithm::Inplace, $statement->algorithm);
        self::assertSame(\SqlSemantics\Model\Definition\IndexLock::None, $statement->lock);
        self::assertSame('DROP INDEX `ix` ON `t` ALGORITHM = INPLACE LOCK = NONE', $statement->toString());
        $plain = $binder->bind('DROP INDEX ix ON t');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\DropTableIndexStatement::class, $plain);
        self::assertSame(\SqlSemantics\Model\Definition\IndexAlgorithm::Default, $plain->algorithm);
        self::assertSame(\SqlSemantics\Model\Definition\IndexLock::Default, $plain->lock);
    }

    public function testBindReadsConcurrentIndexDeletion(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP INDEX CONCURRENTLY IF EXISTS ix');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\DropIndexConcurrentlyStatement::class, $statement);
        self::assertSame(['ix'], $statement->name->parts);
        self::assertTrue($statement->ifExists);
        self::assertSame('DROP INDEX CONCURRENTLY IF EXISTS "ix"', $statement->toString());
    }

    #[TestWith(['DROP INDEX CONCURRENTLY ix CASCADE'])]
    #[TestWith(['DROP INDEX CONCURRENTLY ix, iy'])]
    public function testBindRejectsCascadingOrMultipleConcurrentDeletions(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::ConcurrentIndexDrop->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
    }

    public function testBindRequiresTheOwningTableForPostgreSqlTriggers(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('DROP TRIGGER IF EXISTS tr ON t CASCADE');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\DropTableTriggerStatement::class, $statement);
        self::assertSame('tr', $statement->name);
        self::assertSame(['t'], $statement->table->parts);
        self::assertTrue($statement->ifExists);
        self::assertSame(\SqlSemantics\Model\Definition\DropBehavior::Cascade, $statement->behavior);
        $qualified = $binder->bind('DROP TRIGGER tr ON s.t');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\DropTableTriggerStatement::class, $qualified);
        self::assertSame(['s', 't'], $qualified->table->parts);
        self::assertSame(\SqlSemantics\Model\Definition\DropBehavior::Default, $qualified->behavior);
        self::assertSame('DROP TRIGGER "tr" ON "s"."t"', $qualified->toString());
    }

    public function testBindLeavesOtherDropFormsToTheirOwnBinders(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('DROP TABLE t');
        self::assertNotInstanceOf(\SqlSemantics\Model\Statement\Definition\DropTableTriggerStatement::class, $statement);
        self::assertNotInstanceOf(\SqlSemantics\Model\Statement\Definition\DropIndexConcurrentlyStatement::class, $statement);
    }
}
