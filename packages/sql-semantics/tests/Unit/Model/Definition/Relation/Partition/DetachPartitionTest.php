<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Relation\Partition\DetachPartition::class)]
#[Medium]
final class DetachPartitionTest extends TestCase
{
    #[TestWith(['ALTER TABLE t DETACH PARTITION t_a CONCURRENTLY', Relation\Partition\PartitionDetachMode::Concurrently, 'ALTER TABLE "t" DETACH PARTITION "t_a" CONCURRENTLY'])]
    #[TestWith(['ALTER TABLE t DETACH PARTITION t_a FINALIZE', Relation\Partition\PartitionDetachMode::Finalize, 'ALTER TABLE "t" DETACH PARTITION "t_a" FINALIZE'])]
    #[TestWith(['ALTER TABLE t DETACH PARTITION t_a', Relation\Partition\PartitionDetachMode::Immediate, 'ALTER TABLE "t" DETACH PARTITION "t_a"'])]
    public function testBindsEachDetachMode(string $sql, Relation\Partition\PartitionDetachMode $mode, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind($sql);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertEquals(new Relation\Partition\DetachPartition(new QualifiedName(['t_a']), $mode), $statement->actions[0]);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testRejectsAnOverQualifiedPartition(): void
    {
        $this->expectException(InvalidStructure::class);
        new Relation\Partition\DetachPartition(new QualifiedName(['a', 'b', 'c', 'd']));
    }
}
