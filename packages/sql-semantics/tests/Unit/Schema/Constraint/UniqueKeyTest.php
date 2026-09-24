<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Constraint\UniqueKey;
use SqlSemantics\Schema\ConstraintKind;
use SqlSemantics\Schema\Index\ColumnKey;
use SqlSemantics\SchemaBuilder;

#[CoversClass(UniqueKey::class)]
#[Medium]
final class UniqueKeyTest extends TestCase
{
    public function testLocalColumnsFollowTheDeclaredKeyOrder(): void
    {
        $first = Expression::reference(['b'], Dialect::MySql);
        $second = Expression::reference(['a'], Dialect::MySql);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference::class, $first);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference::class, $second);
        $key = new UniqueKey([new ColumnKey($first), new ColumnKey($second)]);
        self::assertSame(['b', 'a'], $key->localColumns());
        self::assertSame(ConstraintKind::Unique, $key->kind);
        self::assertNull($key->name);
    }

    public function testRejectsAnEmptyKey(): void
    {
        $this->expectException(InvalidStructure::class);
        new UniqueKey([]);
    }

    public function testBindsNullsNotDistinct(): void
    {
        $key = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, UNIQUE NULLS NOT DISTINCT (a))')->tables[0]->constraints[0];
        self::assertInstanceOf(UniqueKey::class, $key);
        self::assertFalse($key->nullsDistinct);
        self::assertSame(['a'], $key->localColumns());
    }

    public function testDefaultsToImmediateDistinctNullsAndAnEmptySource(): void
    {
        $column = Expression::reference(['a'], Dialect::MySql);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference::class, $column);
        $key = new UniqueKey([new ColumnKey($column)]);
        self::assertTrue($key->nullsDistinct);
        self::assertSame(\SqlSemantics\Schema\Constraint\CheckingTime::Immediate, $key->checking);
        self::assertSame('constraint', $key->source->name);
        self::assertSame(0, $key->source->ordinal);
        self::assertSame([], $key->source->children);
    }

    public function testLocalColumnsSkipExpressionKeys(): void
    {
        $key = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT, b INT, UNIQUE KEY k (b, (a + 1), a))')->tables[0]->constraints[0];
        self::assertInstanceOf(UniqueKey::class, $key);
        self::assertSame(['b', 'a'], $key->localColumns());
    }
}
