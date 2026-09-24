<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\Column\AutoIncrementColumn;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Columns;

#[CoversClass(AutoIncrementColumn::class)]
#[Medium]
final class AutoIncrementColumnTest extends TestCase
{
    public function testExpressionsAreEmptyBecauseTheEngineSuppliesTheValue(): void
    {
        self::assertSame([], (new AutoIncrementColumn())->expressions());
    }

    public function testBindsTheMySqlAutoIncrementAttribute(): void
    {
        $column = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT AUTO_INCREMENT PRIMARY KEY)')->tables[0]->columns[0];
        self::assertInstanceOf(AutoIncrementColumn::class, $column->generation);
        self::assertSame('`id` integer NOT NULL AUTO_INCREMENT', Columns::write($column, Dialect::MySql)->toString());
    }
}
