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
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Relation\Partition\HashPartitionBound::class)]
#[Medium]
final class HashPartitionBoundTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ATTACH PARTITION t_p1 FOR VALUES WITH (MODULUS 4, REMAINDER 1)', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Partition\AttachPartition::class, $statement->actions[0]);
        self::assertEquals(new Relation\Partition\HashPartitionBound(4, 1), $statement->actions[0]->bound);
        self::assertSame('ALTER TABLE "t" ATTACH PARTITION "t_p1" FOR VALUES WITH(MODULUS 4, REMAINDER 1)', $statement->toString());
    }

    #[TestWith([4, 4])]
    #[TestWith([0, 0])]
    #[TestWith([4, -1])]
    public function testRejectsARemainderOutsideTheModulus(int $modulus, int $remainder): void
    {
        $this->expectException(InvalidStructure::class);
        new Relation\Partition\HashPartitionBound($modulus, $remainder);
    }
}
