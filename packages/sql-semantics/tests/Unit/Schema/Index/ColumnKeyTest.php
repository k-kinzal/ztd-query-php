<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Index\ColumnKey;
use SqlSemantics\Schema\Index\Direction;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ColumnKey::class)]
#[Medium]
final class ColumnKeyTest extends TestCase
{
    public function testValueIsTheReferencedColumn(): void
    {
        $reference = Expression::reference(['name'], Dialect::MySql);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference::class, $reference);
        $key = new ColumnKey($reference, 10, Direction::Descending);
        self::assertSame($reference, $key->value());
        self::assertSame(10, $key->prefixLength);
        self::assertSame(Direction::Descending, $key->direction);
        self::assertNull($key->nulls);
        self::assertSame([], $key->operatorParameters);
        self::assertSame('index_key', $key->source->name);
    }

    public function testRejectsANonPositivePrefix(): void
    {
        $reference = Expression::reference(['name'], Dialect::MySql);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference::class, $reference);
        $this->expectException(InvalidStructure::class);
        new ColumnKey($reference, 0);
    }

    public function testBindsAPrefixedKeyAgainstTheDeclaredColumn(): void
    {
        $key = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(name VARCHAR(100), KEY ix (name(10) DESC))')->tables[0]->indexes[0]->elements[0];
        self::assertInstanceOf(ColumnKey::class, $key);
        self::assertSame(10, $key->prefixLength);
        self::assertSame(Direction::Descending, $key->direction);
        self::assertSame('name', $key->value()->columnBinding()?->column->name);
    }
}
