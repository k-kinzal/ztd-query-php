<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\Index\Direction;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Direction::class)]
#[Medium]
final class DirectionTest extends TestCase
{
    public function testRepresentsBothOrderingsWithTheirKeywords(): void
    {
        self::assertSame(['ASC', 'DESC'], array_column(Direction::cases(), 'value'));
    }

    public function testClassifiesDeclaredOrderingsAndLeavesOmittedOnesNull(): void
    {
        $index = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b INTEGER, c INTEGER); CREATE INDEX ix ON t(a DESC, b ASC, c)')->tables[0]->indexes[0];
        self::assertSame([Direction::Descending, Direction::Ascending, null], array_column($index->elements, 'direction'));
    }
}
