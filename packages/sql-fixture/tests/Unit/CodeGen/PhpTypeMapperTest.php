<?php

declare(strict_types=1);

namespace Tests\Unit\CodeGen;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\CodeGen\PhpTypeMapper;
use SqlFixture\Schema\ColumnDefinition;

#[CoversClass(PhpTypeMapper::class)]
#[UsesClass(ColumnDefinition::class)]
final class PhpTypeMapperTest extends TestCase
{
    #[Test]
    #[DataProvider('providerNativeTypes')]
    public function nativeTypes(string $sqlType, string $expected): void
    {
        self::assertSame($expected, (new PhpTypeMapper())->nativeType(new ColumnDefinition('c', $sqlType)));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerNativeTypes(): array
    {
        return [
            'int' => ['INT', 'int'],
            'bigint' => ['BIGINT', 'int'],
            'serial' => ['SERIAL', 'int'],
            'year' => ['YEAR', 'int'],
            'float' => ['FLOAT', 'float'],
            'double' => ['DOUBLE', 'float'],
            'bool' => ['BOOLEAN', 'bool'],
            'varchar' => ['VARCHAR', 'string'],
            'text' => ['TEXT', 'string'],
            'datetime' => ['DATETIME', 'string'],
            'json' => ['JSON', 'string'],
            'decimal' => ['DECIMAL', 'string'],
            'blob' => ['BLOB', 'string'],
            'enum' => ['ENUM', 'string'],
            'lower case' => ['int', 'int'],
            'unknown' => ['SOMETHING_ODD', 'mixed'],
        ];
    }

    #[Test]
    public function aNullableColumnIsDocumentedAsNullable(): void
    {
        $column = new ColumnDefinition('c', 'INT', nullable: true);

        self::assertSame('int|null', (new PhpTypeMapper())->documentedType($column));
    }

    #[Test]
    public function aNotNullColumnIsDocumentedWithoutNull(): void
    {
        $column = new ColumnDefinition('c', 'INT', nullable: false);

        self::assertSame('int', (new PhpTypeMapper())->documentedType($column));
    }

    #[Test]
    public function anEnumIsDocumentedAsItsOwnValues(): void
    {
        $column = new ColumnDefinition('status', 'ENUM', nullable: false, enumValues: ['paid', 'pending']);

        self::assertSame("'paid'|'pending'", (new PhpTypeMapper())->documentedType($column));
    }

    #[Test]
    public function anEnumWithNoValuesFallsBackToString(): void
    {
        $column = new ColumnDefinition('status', 'ENUM', nullable: false, enumValues: []);

        self::assertSame('string', (new PhpTypeMapper())->documentedType($column));
    }

    #[Test]
    public function anEnumValueWithAQuoteIsEscaped(): void
    {
        $column = new ColumnDefinition('status', 'ENUM', nullable: false, enumValues: ["it's"]);

        self::assertSame("'it\\'s'", (new PhpTypeMapper())->documentedType($column));
    }

    #[Test]
    public function anOverrideIsAlwaysOptionalWhateverTheColumnAllows(): void
    {
        $required = new ColumnDefinition('status', 'VARCHAR', nullable: false);

        self::assertSame('string|null', (new PhpTypeMapper())->overrideType($required));
    }

    #[Test]
    public function anEnumOverrideKeepsItsValues(): void
    {
        $column = new ColumnDefinition('status', 'ENUM', nullable: false, enumValues: ['paid']);

        self::assertSame("'paid'|null", (new PhpTypeMapper())->overrideType($column));
    }

    #[Test]
    public function anUnknownTypeStaysMixed(): void
    {
        $column = new ColumnDefinition('c', 'SOMETHING_ODD', nullable: true);

        self::assertSame('mixed', (new PhpTypeMapper())->documentedType($column));
        self::assertSame('mixed', (new PhpTypeMapper())->overrideType($column));
    }
}
