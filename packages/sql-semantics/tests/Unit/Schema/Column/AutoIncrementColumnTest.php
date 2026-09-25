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

    public function testSerialDefaultRecordsTheMySqlSerialDefaultValueAttribute(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT SERIAL DEFAULT VALUE, b INT AUTO_INCREMENT UNIQUE)')->tables[0];
        self::assertInstanceOf(AutoIncrementColumn::class, $table->columns[0]->generation);
        self::assertTrue($table->columns[0]->generation->serialDefault);
        self::assertSame(\SqlSemantics\Type\Nullability::NotNull, $table->columns[0]->nullability);
        self::assertInstanceOf(AutoIncrementColumn::class, $table->columns[1]->generation);
        self::assertFalse($table->columns[1]->generation->serialDefault);
        self::assertSame([['a'], ['b']], array_map(static fn (\SqlSemantics\Schema\TableConstraint $constraint): array => $constraint->localColumns(), $table->constraints));
        self::assertFalse((new AutoIncrementColumn())->serialDefault);
    }
}
