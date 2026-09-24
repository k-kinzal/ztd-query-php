<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Schema\Column\ComputedColumn;
use SqlSemantics\Schema\Column\GeneratedStorage;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Columns;

#[CoversClass(ComputedColumn::class)]
#[Medium]
final class ComputedColumnTest extends TestCase
{
    public function testExpressionsContainOnlyTheComputation(): void
    {
        $expression = Expression::binary('+', Expression::reference(['a'], Dialect::MySql), Expression::literal(1, Dialect::MySql));
        $column = new ComputedColumn($expression, GeneratedStorage::Virtual);
        self::assertSame([$expression], $column->expressions());
        self::assertSame(GeneratedStorage::Virtual, $column->storage);
    }

    public function testBindsAStoredGeneratedColumn(): void
    {
        $column = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT, b INT GENERATED ALWAYS AS (a * 2) STORED)')->tables[0]->columns[1];
        self::assertInstanceOf(ComputedColumn::class, $column->generation);
        self::assertSame(GeneratedStorage::Stored, $column->generation->storage);
        self::assertSame('(`a` * 2)', $column->generation->expression->structure()->toString());
        self::assertSame('`b` integer GENERATED ALWAYS AS((`a` * 2)) STORED', Columns::write($column, Dialect::MySql)->toString());
    }
}
