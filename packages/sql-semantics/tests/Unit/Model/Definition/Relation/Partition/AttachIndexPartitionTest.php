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

#[CoversClass(Relation\Partition\AttachIndexPartition::class)]
#[Medium]
final class AttachIndexPartitionTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER INDEX t_id_idx ATTACH PARTITION app.t_a_id_idx', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertEquals(new Relation\Partition\AttachIndexPartition(new QualifiedName(['app', 't_a_id_idx'])), $statement->actions[0]);
        self::assertSame('ALTER INDEX "t_id_idx" ATTACH PARTITION "app"."t_a_id_idx"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testRejectsAnOverQualifiedIndex(): void
    {
        $this->expectException(InvalidStructure::class);
        new Relation\Partition\AttachIndexPartition(new QualifiedName(['a', 'b', 'c', 'd']));
    }
}
