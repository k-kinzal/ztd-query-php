<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Column;

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

#[CoversClass(Relation\Column\SetColumnStatistics::class)]
#[Medium]
final class SetColumnStatisticsTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER INDEX ix ALTER COLUMN 2 SET STATISTICS 500', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertEquals(new Relation\Column\SetColumnStatistics(2, 500), $statement->actions[0]);
        self::assertSame('ALTER INDEX "ix" ALTER COLUMN 2 SET STATISTICS 500', $statement->toString());
    }

    public function testBindsTheDefaultTargetAsMinusOne(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER id SET STATISTICS DEFAULT', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertEquals(new Relation\Column\SetColumnStatistics('id', -1), $statement->actions[0]);
        self::assertSame('ALTER TABLE "t" ALTER COLUMN "id" SET STATISTICS DEFAULT', $statement->toString());
    }

    #[TestWith([0, 1])]
    #[TestWith([32768, 1])]
    #[TestWith(['id', -2])]
    #[TestWith(['id', 10001])]
    #[TestWith(['', 1])]
    public function testRejectsAPositionOrTargetOutOfRange(string|int $column, int $target): void
    {
        $this->expectException(InvalidStructure::class);
        new Relation\Column\SetColumnStatistics($column, $target);
    }
}
