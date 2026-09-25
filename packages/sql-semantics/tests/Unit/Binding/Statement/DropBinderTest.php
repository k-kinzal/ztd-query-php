<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\DropBinder;
use SqlSemantics\Binding\TableResolver;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\IndexAlgorithm;
use SqlSemantics\Model\Definition\IndexLock;
use SqlSemantics\Model\Statement\Definition\DropIndexConcurrentlyStatement;
use SqlSemantics\Model\Statement\Origin;
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
        self::assertSame(IndexAlgorithm::Inplace, $statement->algorithm);
        self::assertSame(IndexLock::None, $statement->lock);
        self::assertSame('DROP INDEX `ix` ON `t` ALGORITHM = INPLACE LOCK = NONE', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        $plain = $binder->bind('DROP INDEX ix ON t');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\DropTableIndexStatement::class, $plain);
        self::assertSame(IndexAlgorithm::Default, $plain->algorithm);
        self::assertSame(IndexLock::Default, $plain->lock);
    }

    public function testBindReadsConcurrentIndexDeletion(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP INDEX CONCURRENTLY IF EXISTS ix');
        self::assertInstanceOf(DropIndexConcurrentlyStatement::class, $statement);
        self::assertSame(['ix'], $statement->name->parts);
        self::assertTrue($statement->ifExists);
        self::assertSame('DROP INDEX CONCURRENTLY IF EXISTS "ix"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
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
        self::assertSame('DROP TRIGGER "tr" ON "s"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($qualified));
    }

    public function testBindLeavesOtherDropFormsToTheirOwnBinders(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('DROP TABLE t');
        self::assertNotInstanceOf(\SqlSemantics\Model\Statement\Definition\DropTableTriggerStatement::class, $statement);
        self::assertNotInstanceOf(DropIndexConcurrentlyStatement::class, $statement);
    }

    public function testMysqlIndexReadsLowercasePolicies(): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT, KEY ix(a))'), new Identifiers(Dialect::MySql), ''));
        $node = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('drop index ix on t algorithm=inplace lock=none')->find('drop_index_stmt')[0];
        $statement = DropBinder::mysqlIndex(new Origin('s0', $node, Dialect::MySql), $node, $context);
        self::assertSame([IndexAlgorithm::Inplace, IndexLock::None], [$statement->algorithm, $statement->lock]);
    }

    #[TestWith([Dialect::MySql, 'DROP TABLE t', 'drop_table_stmt'])]
    #[TestWith([Dialect::MySql, 'TRUNCATE TABLE t', 'truncate_stmt'])]
    #[TestWith([Dialect::PostgreSql, 'DROP INDEX ix', 'DropStmt'])]
    #[TestWith([Dialect::MySql, 'DROP TABLE concurrently', 'drop_table_stmt'])]
    public function testBindReturnsNullForOtherRemovals(Dialect $dialect, string $sql, string $rule): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder($dialect))->build(), new Identifiers($dialect), ''));
        $node = (new DialectParser($dialect))->parse($sql)->find($rule)[0];
        self::assertNull(DropBinder::bind(new Origin('s0', $node, $dialect), $node, $context));
    }

    public function testBindReadsLowercaseConcurrentIndexDeletion(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('drop index concurrently ix');
        self::assertInstanceOf(DropIndexConcurrentlyStatement::class, $statement);
        self::assertFalse($statement->ifExists);
    }
}
