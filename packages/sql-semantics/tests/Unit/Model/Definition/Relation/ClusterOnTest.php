<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Relation\ClusterOn::class)]
#[Medium]
final class ClusterOnTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t CLUSTER ON t_pkey', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertEquals(new Relation\ClusterOn('t_pkey'), $statement->actions[0]);
        self::assertSame('ALTER TABLE "t" CLUSTER ON "t_pkey"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testRejectsAnEmptyIndexName(): void
    {
        $this->expectException(InvalidStructure::class);
        new Relation\ClusterOn('');
    }
}
