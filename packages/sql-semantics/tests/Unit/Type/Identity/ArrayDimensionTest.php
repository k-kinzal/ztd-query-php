<?php

declare(strict_types=1);

namespace Tests\Unit\Type\Identity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Identity\ArrayDimension;
use SqlSemantics\Type\Identity\ArrayStorage;
use SqlSemantics\Type\Identity\Numeric\NumericParameter;

#[CoversClass(ArrayDimension::class)]
#[Medium]
final class ArrayDimensionTest extends TestCase
{
    public function testAnUnboundedDimensionHasNoLength(): void
    {
        self::assertNull((new ArrayDimension())->length);
        self::assertSame('3', (new ArrayDimension(new NumericParameter('3')))->length?->spelling);
    }

    public function testBindsDeclaredBoundsInOrder(): void
    {
        $identity = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER[3][], b TEXT ARRAY)')->tables[0]->columns[0]->type->identity;
        self::assertInstanceOf(ArrayStorage::class, $identity);
        self::assertCount(2, $identity->dimensions);
        self::assertSame('3', $identity->dimensions[0]->length?->spelling);
        self::assertNull($identity->dimensions[1]->length);
    }
}
