<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Foreign;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation\Foreign;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\CreateForeignPartitionStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Foreign\PartitionColumn::class)]
#[Medium]
final class PartitionColumnTest extends TestCase
{
    public function testRetainsTheOverriddenAttributes(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE p(a INTEGER)')))->bind('CREATE FOREIGN TABLE ft PARTITION OF p (a WITH OPTIONS NOT NULL DEFAULT 1 CHECK (a > 0)) DEFAULT SERVER s');
        self::assertInstanceOf(CreateForeignPartitionStatement::class, $statement);
        self::assertSame('a', $statement->columns[0]->column);
        self::assertSame(\SqlSemantics\Type\Nullability::NotNull, $statement->columns[0]->nullability);
        self::assertInstanceOf(\SqlSemantics\Schema\Column\SuppliedColumn::class, $statement->columns[0]->generation);
        self::assertCount(1, $statement->columns[0]->constraints);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE p(a INTEGER)')))->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testRejectsAnUnknownNullability(): void
    {
        $this->expectException(InvalidStructure::class);
        new Foreign\PartitionColumn('a', \SqlSemantics\Type\Nullability::Unknown);
    }

    public function testRejectsAnOverQualifiedCollation(): void
    {
        $this->expectException(InvalidStructure::class);
        new Foreign\PartitionColumn('a', collation: new QualifiedName(['a', 'b', 'c']));
    }
}
