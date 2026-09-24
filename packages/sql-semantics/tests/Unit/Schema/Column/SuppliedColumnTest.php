<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Schema\Column\SuppliedColumn;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SuppliedColumn::class)]
#[Medium]
final class SuppliedColumnTest extends TestCase
{
    public function testExpressionsOmitAbsentDefaultsAndKeepDeclaredOnes(): void
    {
        $default = Expression::literal(1, Dialect::MySql);
        $onUpdate = Expression::literal(2, Dialect::MySql);
        self::assertSame([], (new SuppliedColumn())->expressions());
        self::assertSame([$onUpdate], (new SuppliedColumn(null, $onUpdate))->expressions());
        self::assertSame([$default, $onUpdate], (new SuppliedColumn($default, $onUpdate))->expressions());
    }

    public function testBindsDefaultAndOnUpdateExpressions(): void
    {
        $column = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)')->tables[0]->columns[0];
        self::assertInstanceOf(SuppliedColumn::class, $column->generation);
        self::assertSame('CURRENT_TIMESTAMP', $column->generation->default?->structure()->toString());
        self::assertSame('CURRENT_TIMESTAMP', $column->generation->onUpdate?->structure()->toString());
    }
}
