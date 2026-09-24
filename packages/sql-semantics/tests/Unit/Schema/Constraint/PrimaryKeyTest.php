<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Constraint\CheckingTime;
use SqlSemantics\Schema\Constraint\PrimaryKey;
use SqlSemantics\Schema\ConstraintKind;
use SqlSemantics\Schema\Index\ColumnKey;
use SqlSemantics\Schema\Index\ExpressionKey;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PrimaryKey::class)]
#[Medium]
final class PrimaryKeyTest extends TestCase
{
    public function testLocalColumnsListOnlyColumnKeys(): void
    {
        $reference = Expression::reference(['name'], Dialect::MySql);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference::class, $reference);
        $key = new PrimaryKey([new ColumnKey($reference), new ExpressionKey(Expression::literal(1, Dialect::MySql))], name: 'pk');
        self::assertSame(['name'], $key->localColumns());
        self::assertSame(ConstraintKind::PrimaryKey, $key->kind);
        self::assertSame('pk', $key->name);
        self::assertTrue($key->nullsDistinct);
        self::assertCount(2, $key->keys);
    }

    public function testRejectsAnEmptyKey(): void
    {
        $this->expectException(InvalidStructure::class);
        new PrimaryKey([]);
    }

    public function testBindsAnOrderedDeferrableKey(): void
    {
        $key = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b INTEGER, CONSTRAINT pk PRIMARY KEY (b, a) DEFERRABLE)')->tables[0]->constraints[0];
        self::assertInstanceOf(PrimaryKey::class, $key);
        self::assertSame(['b', 'a'], $key->localColumns());
        self::assertSame(CheckingTime::DeferrableImmediate, $key->checking);
        self::assertSame('pk', $key->name);
    }
}
