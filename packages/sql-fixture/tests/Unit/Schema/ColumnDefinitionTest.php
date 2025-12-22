<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlFixture\Schema\ColumnDefinition;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ColumnDefinition::class)]
final class ColumnDefinitionTest extends TestCase
{
    #[Test]
    public function constructsWithDefaults(): void
    {
        $column = new ColumnDefinition('id', 'INT');
        self::assertSame('id', $column->name);
        self::assertSame('INT', $column->type);
        self::assertNull($column->length);
        self::assertNull($column->precision);
        self::assertNull($column->scale);
        self::assertTrue($column->nullable);
        self::assertFalse($column->unsigned);
        self::assertNull($column->default);
        self::assertFalse($column->autoIncrement);
        self::assertFalse($column->generated);
        self::assertNull($column->enumValues);
    }

    #[Test]
    public function constructsWithAllParameters(): void
    {
        $column = new ColumnDefinition(
            name: 'price',
            type: 'DECIMAL',
            length: null,
            precision: 10,
            scale: 2,
            nullable: false,
            unsigned: true,
            default: '0.00',
            autoIncrement: false,
            generated: false,
            enumValues: null,
        );
        self::assertSame('price', $column->name);
        self::assertSame('DECIMAL', $column->type);
        self::assertSame(10, $column->precision);
        self::assertSame(2, $column->scale);
        self::assertFalse($column->nullable);
        self::assertTrue($column->unsigned);
        self::assertSame('0.00', $column->default);
    }

    #[Test]
    public function constructsWithEnumValues(): void
    {
        $column = new ColumnDefinition(
            name: 'status',
            type: 'ENUM',
            enumValues: ['active', 'inactive', 'pending'],
        );
        self::assertSame(['active', 'inactive', 'pending'], $column->enumValues);
    }

    #[Test]
    public function constructsWithAutoIncrement(): void
    {
        $column = new ColumnDefinition(
            name: 'id',
            type: 'INT',
            autoIncrement: true,
        );
        self::assertTrue($column->autoIncrement);
    }

    #[Test]
    public function constructsWithGenerated(): void
    {
        $column = new ColumnDefinition(
            name: 'full_name',
            type: 'VARCHAR',
            generated: true,
        );
        self::assertTrue($column->generated);
    }
}
