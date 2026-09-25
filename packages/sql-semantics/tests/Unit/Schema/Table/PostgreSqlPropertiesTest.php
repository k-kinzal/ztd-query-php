<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\Table\CommitAction;
use SqlSemantics\Schema\Table\Persistence;
use SqlSemantics\Schema\Table\PostgreSqlProperties;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Storage;

#[CoversClass(PostgreSqlProperties::class)]
#[Medium]
final class PostgreSqlPropertiesTest extends TestCase
{
    public function testDialectIsPostgreSql(): void
    {
        $properties = new PostgreSqlProperties();
        self::assertSame(Dialect::PostgreSql, $properties->dialect());
        self::assertSame(Persistence::Permanent, $properties->persistence);
        self::assertSame(CommitAction::PreserveRows, $properties->onCommit);
        self::assertNull($properties->accessMethod);
        self::assertSame([], $properties->storageParameters);
    }

    public function testBindsPersistenceCommitBehaviorAndStorage(): void
    {
        $properties = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TEMP TABLE t(id INTEGER) USING heap WITH (fillfactor = 70) ON COMMIT DROP TABLESPACE ts')->tables[0]->properties;
        self::assertInstanceOf(PostgreSqlProperties::class, $properties);
        self::assertSame(Persistence::Temporary, $properties->persistence);
        self::assertSame(CommitAction::Drop, $properties->onCommit);
        self::assertSame('heap', $properties->accessMethod);
        self::assertSame('ts', $properties->tablespace);
        self::assertCount(1, $properties->storageParameters);
        self::assertSame('USING "heap" WITH ("fillfactor" = 70) ON COMMIT DROP TABLESPACE "ts"', Storage::table($properties, Dialect::PostgreSql)->toString());
    }

    public function testSerializesConstructedProperties(): void
    {
        $properties = new PostgreSqlProperties(Persistence::Temporary, CommitAction::Drop, 'heap');
        self::assertSame('USING "heap" ON COMMIT DROP', Storage::table($properties, Dialect::PostgreSql)->toString());
    }

    public function testRejectsACommitActionOfATableThatIsNotTemporary(): void
    {
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new PostgreSqlProperties(Persistence::Unlogged, CommitAction::DeleteRows);
    }

    public function testRetainsThePartitioningScheme(): void
    {
        $properties = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE UNLOGGED TABLE p(id INTEGER) PARTITION BY LIST (id)')->tables[0]->properties;
        self::assertInstanceOf(PostgreSqlProperties::class, $properties);
        self::assertSame(Persistence::Unlogged, $properties->persistence);
        self::assertSame(\SqlSemantics\Schema\Partition\PartitionStrategy::List, $properties->partitioning?->strategy);
    }

    public function testRejectsStorageParametersOnAPartitionedTable(): void
    {
        $scheme = new \SqlSemantics\Schema\Partition\PartitionScheme(\SqlSemantics\Schema\Partition\PartitionStrategy::Hash, [new \SqlSemantics\Schema\Partition\PartitionKey(\SqlSemantics\Model\Expression::literal(1, Dialect::PostgreSql))]);
        $parameter = new \SqlSemantics\Schema\Storage\Parameter(new \SqlSemantics\Model\Relation\QualifiedName(['fillfactor']), \SqlSemantics\Schema\Storage\ImpliedSetting::Enabled);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new PostgreSqlProperties(storageParameters: [$parameter], partitioning: $scheme);
    }

    public function testRetainsTheInheritedParents(): void
    {
        $properties = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE a(x INTEGER); CREATE TABLE b(y INTEGER)', 'CREATE TABLE c(z INTEGER) INHERITS (a, public.b)')->tables[2]->properties;
        self::assertInstanceOf(PostgreSqlProperties::class, $properties);
        self::assertSame([['a'], ['public', 'b']], array_map(static fn (\SqlSemantics\Model\Relation\QualifiedName $parent): array => $parent->parts, $properties->parents));
        self::assertSame('INHERITS("a", "public"."b")', Storage::table($properties, Dialect::PostgreSql)->toString());
    }

    public function testRejectsInheritanceOfAPartitionedTable(): void
    {
        $scheme = new \SqlSemantics\Schema\Partition\PartitionScheme(\SqlSemantics\Schema\Partition\PartitionStrategy::Hash, [new \SqlSemantics\Schema\Partition\PartitionKey(\SqlSemantics\Model\Expression::literal(1, Dialect::PostgreSql))]);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new PostgreSqlProperties(partitioning: $scheme, parents: [new \SqlSemantics\Model\Relation\QualifiedName(['a'])]);
    }

    public function testRejectsAParentNamedTwice(): void
    {
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $this->expectExceptionMessage('A table inherits from each parent once.');
        new PostgreSqlProperties(parents: [new \SqlSemantics\Model\Relation\QualifiedName(['a']), new \SqlSemantics\Model\Relation\QualifiedName(['a'])]);
    }
}
