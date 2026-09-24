<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\Relation\PartitionActions;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PartitionActions::class)]
#[Medium]
final class PartitionActionsTest extends TestCase
{
    /**
     * @param class-string<object> $class
     */
    #[TestWith(['ALTER TABLE t ATTACH PARTITION p FOR VALUES IN (1)', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" ATTACH PARTITION "p" FOR VALUES IN(1)'])]
    #[TestWith(['ALTER TABLE t DETACH PARTITION p FINALIZE', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" DETACH PARTITION "p" FINALIZE'])]
    #[TestWith(['ALTER INDEX ix ATTACH PARTITION iy', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER INDEX "ix" ATTACH PARTITION "iy"'])]
    public function testReadReadsAttachAndDetach(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected, strict: false)->toString());
    }

    /**
     * @param class-string<object> $class
     */
    #[TestWith(['ALTER TABLE t ATTACH PARTITION p DEFAULT', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" ATTACH PARTITION "p" DEFAULT'])]
    #[TestWith(['ALTER TABLE t ATTACH PARTITION p FOR VALUES IN (1, 2)', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" ATTACH PARTITION "p" FOR VALUES IN(1, 2)'])]
    public function testBoundReadsEachBoundForm(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected, strict: false)->toString());
    }

    /**
     * @param class-string<object> $class
     */
    #[TestWith(['ALTER TABLE t ATTACH PARTITION p FOR VALUES WITH (REMAINDER 0, MODULUS 2)', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" ATTACH PARTITION "p" FOR VALUES WITH(MODULUS 2, REMAINDER 0)'])]
    public function testHashReadsModulusAndRemainder(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected, strict: false)->toString());
    }

    /**
     * @param class-string<object> $class
     */
    #[TestWith(['ALTER TABLE t ATTACH PARTITION p FOR VALUES FROM (MINVALUE, 1) TO (5, MAXVALUE)', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" ATTACH PARTITION "p" FOR VALUES FROM(MINVALUE, 1) TO(5, MAXVALUE)'])]
    public function testRangeReadsBothEnds(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected, strict: false)->toString());
    }

    /**
     * @param class-string<object> $class
     */
    #[TestWith(['ALTER TABLE t ATTACH PARTITION p FOR VALUES IN (\'a\', 1 + 1)', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" ATTACH PARTITION "p" FOR VALUES IN(\'a\', (1 + 1))'])]
    public function testExpressionsBindsEachValue(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected, strict: false)->toString());
    }

    #[TestWith(['ALTER TABLE t ATTACH PARTITION p FOR VALUES WITH (MODULUS 2, REMAINDER 2)'])]
    public function testHashRejectsARemainderAtTheModulus(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::PartitionBound->message());
        $binder->bind($sql, strict: false);
    }

    #[TestWith(['ALTER TABLE t ATTACH PARTITION p FOR VALUES FROM (1) TO (2, 3)'])]
    public function testRangeRejectsEndsOfDifferentWidths(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::PartitionBound->message());
        $binder->bind($sql, strict: false);
    }

    #[TestWith(['ALTER TABLE t DETACH PARTITION p CONCURRENTLY', 'ALTER TABLE "t" DETACH PARTITION "p" CONCURRENTLY'])]
    #[TestWith(['ALTER TABLE t DETACH PARTITION p', 'ALTER TABLE "t" DETACH PARTITION "p"'])]
    #[TestWith(['ALTER TABLE t ATTACH PARTITION d.s.p DEFAULT', 'ALTER TABLE "t" ATTACH PARTITION "d"."s"."p" DEFAULT'])]
    #[TestWith(['ALTER TABLE t ATTACH PARTITION p FOR VALUES WITH (modulus 1_000, remainder 0)', 'ALTER TABLE "t" ATTACH PARTITION "p" FOR VALUES WITH(MODULUS 1000, REMAINDER 0)'])]
    #[TestWith(['ALTER TABLE t ATTACH PARTITION p FOR VALUES WITH (modulus 123456789, remainder 0)', 'ALTER TABLE "t" ATTACH PARTITION "p" FOR VALUES WITH(MODULUS 123456789, REMAINDER 0)'])]
    #[TestWith(['ALTER TABLE t ATTACH PARTITION p FOR VALUES FROM (minvalue) TO (MAXVALUE)', 'ALTER TABLE "t" ATTACH PARTITION "p" FOR VALUES FROM(MINVALUE) TO(MAXVALUE)'])]
    public function testReadSpellsEachPartitionCommand(string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind($sql, strict: false);
        self::assertSame($expected, $statement->toString());
    }

    #[TestWith(['ALTER TABLE t ATTACH PARTITION a.b.c.d DEFAULT'])]
    #[TestWith(['ALTER TABLE t ATTACH PARTITION p FOR VALUES WITH (modulus 1234567890, remainder 0)'])]
    #[TestWith(['ALTER TABLE t ATTACH PARTITION p FOR VALUES WITH (modulus 4, modulus 5, remainder 0)'])]
    #[TestWith(['ALTER TABLE t ATTACH PARTITION p FOR VALUES WITH (modulus 4, remainder 0, size 2)'])]
    #[TestWith(['ALTER TABLE t ATTACH PARTITION p FOR VALUES WITH (modulus 4)'])]
    public function testReadRejectsMalformedPartitions(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)'));
        $this->expectException(InvalidSql::class);
        $binder->bind($sql, strict: false);
    }

    public function testBoundClassifiesEachBoundForm(): void
    {
        $scope = new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql));
        $specs = \SqlSemantics\Ast\Tree::outer((new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse('ALTER TABLE t ATTACH PARTITION p FOR VALUES IN (1, 2); ALTER TABLE t ATTACH PARTITION q FOR VALUES WITH (modulus 2, remainder 1); ALTER TABLE t ATTACH PARTITION r FOR VALUES FROM (1) TO (maxvalue)'), ['PartitionBoundSpec']);
        $list = PartitionActions::bound($specs[0], $scope);
        self::assertInstanceOf(\SqlSemantics\Model\Definition\Relation\Partition\ListPartitionBound::class, $list);
        self::assertCount(2, PartitionActions::expressions(\SqlSemantics\Ast\Tree::outer($specs[0], ['expr_list'])[0], $scope));
        self::assertSame([2, 1], [PartitionActions::hash($specs[1])->modulus, PartitionActions::hash($specs[1])->remainder]);
        self::assertSame([\SqlSemantics\Model\Definition\Relation\Partition\RangeBoundary::MaxValue], PartitionActions::range($specs[2], $scope)->to);
    }
}
