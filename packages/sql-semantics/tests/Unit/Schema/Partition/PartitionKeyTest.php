<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Partition\PartitionKey;
use SqlSemantics\Schema\Table\PostgreSqlProperties;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PartitionKey::class)]
#[Medium]
final class PartitionKeyTest extends TestCase
{
    public function testRetainsTheValueCollationAndOperatorClass(): void
    {
        $key = new PartitionKey(Expression::literal(1, Dialect::PostgreSql), new QualifiedName(['pg_catalog', 'C']), new QualifiedName(['int4_ops']));
        self::assertSame('1', $key->value->structure()->toString());
        self::assertSame(['pg_catalog', 'C'], $key->collation?->parts);
        self::assertSame(['int4_ops'], $key->operatorClass?->parts);
    }

    public function testBindsColumnAndExpressionKeysAgainstTheTable(): void
    {
        $properties = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE p(id INTEGER, name TEXT) PARTITION BY HASH (id, (id + 1), lower(name))')->tables[0]->properties;
        self::assertInstanceOf(PostgreSqlProperties::class, $properties);
        $keys = $properties->partitioning->keys ?? [];
        self::assertSame(['id'], $keys[0]->value->referenceParts());
        self::assertSame(['("id" + 1)', '"lower"("name")'], [$keys[1]->value->structure()->toString(), $keys[2]->value->structure()->toString()]);
        self::assertNull($keys[0]->collation);
    }

    public function testRejectsAnotherDialect(): void
    {
        $this->expectException(InvalidStructure::class);
        new PartitionKey(Expression::literal(1, Dialect::MySql));
    }

    public function testRejectsOverqualifiedCollations(): void
    {
        $this->expectException(InvalidStructure::class);
        new PartitionKey(Expression::literal(1, Dialect::PostgreSql), new QualifiedName(['a', 'b', 'c']));
    }

    public function testRejectsOverqualifiedOperatorClasses(): void
    {
        $this->expectException(InvalidStructure::class);
        new PartitionKey(Expression::literal(1, Dialect::PostgreSql), operatorClass: new QualifiedName(['a', 'b', 'c']));
    }
}
