<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Relation\PartitionActions;

#[CoversClass(PartitionActions::class)]
#[Medium]
final class PartitionActionsTest extends TestCase
{
    public function testWriteWritesAttachAndDetach(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind('ALTER TABLE t DETACH PARTITION p CONCURRENTLY', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertSame('DETACH PARTITION "p" CONCURRENTLY', PartitionActions::write($statement->actions[0])?->toString());
    }

    public function testBoundWritesEachBoundForm(): void
    {
        self::assertSame('DEFAULT', PartitionActions::bound(new Relation\Partition\DefaultPartitionBound())->toString());
        self::assertSame('FOR VALUES WITH(MODULUS 4, REMAINDER 3)', PartitionActions::bound(new Relation\Partition\HashPartitionBound(4, 3))->toString());
    }

    public function testEndWritesUnboundedMarkersAsKeywords(): void
    {
        self::assertSame('MINVALUE', PartitionActions::end(Relation\Partition\RangeBoundary::MinValue)->toString());
        self::assertSame('1', PartitionActions::end(\SqlSemantics\Model\Expression::literal(1, Dialect::PostgreSql))->toString());
    }
}
