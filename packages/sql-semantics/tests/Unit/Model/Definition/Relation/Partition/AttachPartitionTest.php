<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Relation\Partition\AttachPartition::class)]
#[Medium]
final class AttachPartitionTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ATTACH PARTITION app.t_a FOR VALUES IN (1)', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Partition\AttachPartition::class, $statement->actions[0]);
        self::assertSame(['app', 't_a'], $statement->actions[0]->partition->parts);
        self::assertInstanceOf(Relation\Partition\ListPartitionBound::class, $statement->actions[0]->bound);
        self::assertSame('ALTER TABLE "t" ATTACH PARTITION "app"."t_a" FOR VALUES IN(1)', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testRejectsAnOverQualifiedPartition(): void
    {
        $this->expectException(InvalidStructure::class);
        new Relation\Partition\AttachPartition(new QualifiedName(['a', 'b', 'c', 'd']), new Relation\Partition\DefaultPartitionBound());
    }
}
