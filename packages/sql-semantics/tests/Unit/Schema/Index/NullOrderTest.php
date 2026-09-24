<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\Index\NullOrder;
use SqlSemantics\SchemaBuilder;

#[CoversClass(NullOrder::class)]
#[Medium]
final class NullOrderTest extends TestCase
{
    public function testRepresentsBothPlacementsWithTheirKeywords(): void
    {
        self::assertSame(['FIRST', 'LAST'], array_column(NullOrder::cases(), 'value'));
    }

    public function testClassifiesDeclaredNullOrderingsAndLeavesOmittedOnesNull(): void
    {
        $index = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b INTEGER, c INTEGER); CREATE INDEX ix ON t(a NULLS FIRST, b DESC NULLS LAST, c)')->tables[0]->indexes[0];
        self::assertSame([NullOrder::First, NullOrder::Last, null], array_column($index->elements, 'nulls'));
    }
}
