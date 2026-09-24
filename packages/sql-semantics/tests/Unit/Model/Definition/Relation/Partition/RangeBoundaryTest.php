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
use SqlSemantics\SchemaBuilder;

#[CoversClass(Relation\Partition\RangeBoundary::class)]
#[Medium]
final class RangeBoundaryTest extends TestCase
{
    public function testSpellsEachChoiceAsItsKeywords(): void
    {
        self::assertSame(['MINVALUE', 'MAXVALUE'], array_column(Relation\Partition\RangeBoundary::cases(), 'value'));
    }

    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ATTACH PARTITION t_hi FOR VALUES FROM (MINVALUE) TO (MAXVALUE)', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Partition\AttachPartition::class, $statement->actions[0]);
        self::assertEquals(new Relation\Partition\RangePartitionBound([Relation\Partition\RangeBoundary::MinValue], [Relation\Partition\RangeBoundary::MaxValue]), $statement->actions[0]->bound);
        self::assertSame('ALTER TABLE "t" ATTACH PARTITION "t_hi" FOR VALUES FROM(MINVALUE) TO(MAXVALUE)', $statement->toString());
    }
}
