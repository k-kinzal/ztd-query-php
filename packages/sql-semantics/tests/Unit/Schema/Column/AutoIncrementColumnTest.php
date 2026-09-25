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
        $tables = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT SERIAL DEFAULT VALUE)', 'CREATE TABLE u(b INT AUTO_INCREMENT UNIQUE)')->tables;
        self::assertInstanceOf(AutoIncrementColumn::class, $tables[0]->columns[0]->generation);
        self::assertTrue($tables[0]->columns[0]->generation->serialDefault);
        self::assertSame(\SqlSemantics\Type\Nullability::NotNull, $tables[0]->columns[0]->nullability);
        self::assertInstanceOf(AutoIncrementColumn::class, $tables[1]->columns[0]->generation);
        self::assertFalse($tables[1]->columns[0]->generation->serialDefault);
        self::assertSame([['a'], ['b']], [$tables[0]->constraints[0]->localColumns(), $tables[1]->constraints[0]->localColumns()]);
        self::assertFalse((new AutoIncrementColumn())->serialDefault);
    }

    public function testSerialTypeRecordsTheMySqlSerialType(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a SERIAL)')->tables[0];
        self::assertInstanceOf(AutoIncrementColumn::class, $table->columns[0]->generation);
        self::assertTrue($table->columns[0]->generation->serialType);
        self::assertFalse($table->columns[0]->generation->serialDefault);
        self::assertFalse((new AutoIncrementColumn())->serialType);
    }

    public function testSerialDefaultAndSerialTypeExcludeEachOther(): void
    {
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new AutoIncrementColumn(true, true);
    }
}
