<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Relation\Partition\ListPartitionBound::class)]
#[Medium]
final class ListPartitionBoundTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind("ALTER TABLE t ATTACH PARTITION t_a FOR VALUES IN ('a', 'b')", strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Partition\AttachPartition::class, $statement->actions[0]);
        self::assertInstanceOf(Relation\Partition\ListPartitionBound::class, $statement->actions[0]->bound);
        self::assertCount(2, $statement->actions[0]->bound->values);
        self::assertSame('ALTER TABLE "t" ATTACH PARTITION "t_a" FOR VALUES IN(\'a\', \'b\')', $statement->toString());
    }

    public function testRejectsAValueFromAnotherDatabaseLanguage(): void
    {
        $this->expectException(InvalidStructure::class);
        new Relation\Partition\ListPartitionBound([\SqlSemantics\Model\Expression::literal(1, Dialect::MySql)]);
    }
}
