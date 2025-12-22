<?php

declare(strict_types=1);

namespace Tests\Unit\TypeMapper;

use Faker\Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\MySqlTypeMapper as PlatformMySqlTypeMapper;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\TypeMapper\MySqlTypeMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(MySqlTypeMapper::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(PlatformMySqlTypeMapper::class)]
final class MySqlTypeMapperTest extends TestCase
{
    #[Test]
    public function generateTinyInt(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'TINYINT', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(-128, $value);
        self::assertLessThanOrEqual(127, $value);
    }

    #[Test]
    public function generateTinyIntUnsigned(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'TINYINT', unsigned: true, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(0, $value);
        self::assertLessThanOrEqual(255, $value);
    }

    #[Test]
    public function generateTinyIntOneAsBoolean(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'TINYINT', length: 1, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsBool($value);
    }

    #[Test]
    public function generateInt(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'INT', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsInt($value);
    }

    #[Test]
    public function generateDecimal(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'DECIMAL', precision: 10, scale: 2, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsFloat($value);
    }

    #[Test]
    public function generateVarchar(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'VARCHAR', length: 100, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertLessThanOrEqual(100, strlen($value));
    }

    #[Test]
    public function generateChar(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'CHAR', length: 5, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertSame(5, strlen($value));
    }

    #[Test]
    public function generateDate(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'DATE', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $value);
    }

    #[Test]
    public function generateTime(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'TIME', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', $value);
    }

    #[Test]
    public function generateDatetime(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'DATETIME', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value);
    }

    #[Test]
    public function generateTimestamp(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'TIMESTAMP', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        $timestamp = strtotime($value);
        self::assertGreaterThanOrEqual(strtotime('1970-01-01'), $timestamp);
        self::assertLessThanOrEqual(strtotime('2038-01-19'), $timestamp);
    }

    #[Test]
    public function generateYear(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'YEAR', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(1901, $value);
        self::assertLessThanOrEqual(2155, $value);
    }

    #[Test]
    public function generateEnum(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'ENUM', enumValues: ['a', 'b', 'c'], nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertContains($value, ['a', 'b', 'c']);
    }

    #[Test]
    public function generateSet(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'SET', enumValues: ['x', 'y', 'z'], nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        $parts = explode(',', $value);
        array_walk($parts, static function (string $part): void {
            self::assertContains($part, ['x', 'y', 'z']);
        });
    }

    #[Test]
    public function generateJson(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'JSON', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        $decoded = json_decode($value, true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('key', $decoded);
        self::assertArrayHasKey('value', $decoded);
    }

    #[Test]
    public function generatePoint(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'POINT', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertStringStartsWith('POINT(', $value);
    }

    #[Test]
    public function generateAutoIncrementReturnsNull(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('id', 'INT', autoIncrement: true, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertNull($value);
    }

    #[Test]
    public function generateGeneratedColumnReturnsNull(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('computed', 'INT', generated: true, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertNull($value);
    }

    #[Test]
    public function generateBit(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'BIT', length: 8, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(0, $value);
        self::assertLessThanOrEqual(255, $value);
    }

    #[Test]
    public function generateBinary(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'BINARY', length: 16, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertSame(16, strlen($value));
    }

    #[Test]
    public function generateBlob(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'BLOB', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertGreaterThan(0, strlen($value));
    }

    #[Test]
    public function generateText(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'TEXT', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertGreaterThan(0, strlen($value));
    }

    #[Test]
    public function generateSmallInt(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'SMALLINT', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(-32768, $value);
        self::assertLessThanOrEqual(32767, $value);
    }

    #[Test]
    public function generateSmallIntUnsigned(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'SMALLINT', unsigned: true, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(0, $value);
        self::assertLessThanOrEqual(65535, $value);
    }

    #[Test]
    public function generateMediumInt(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'MEDIUMINT', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(-8388608, $value);
        self::assertLessThanOrEqual(8388607, $value);
    }

    #[Test]
    public function generateMediumIntUnsigned(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'MEDIUMINT', unsigned: true, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(0, $value);
        self::assertLessThanOrEqual(16777215, $value);
    }

    #[Test]
    public function generateIntUnsigned(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'INT', unsigned: true, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(0, $value);
    }

    #[Test]
    public function generateBigInt(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'BIGINT', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsInt($value);
    }

    #[Test]
    public function generateBigIntUnsigned(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'BIGINT', unsigned: true, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(0, $value);
    }

    #[Test]
    public function generateFloat(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'FLOAT', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsFloat($value);
    }

    #[Test]
    public function generateDouble(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'DOUBLE', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsFloat($value);
    }

    #[Test]
    public function generateDecimalUnsigned(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'DECIMAL', precision: 10, scale: 2, unsigned: true, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(0, $value);
    }

    #[Test]
    public function generateTinyText(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'TINYTEXT', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertLessThanOrEqual(255, strlen($value));
    }

    #[Test]
    public function generateMediumText(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'MEDIUMTEXT', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
    }

    #[Test]
    public function generateLongText(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'LONGTEXT', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
    }

    #[Test]
    public function generateVarbinary(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'VARBINARY', length: 100, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertLessThanOrEqual(100, strlen($value));
    }

    #[Test]
    public function generateTinyBlob(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'TINYBLOB', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
    }

    #[Test]
    public function generateMediumBlob(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'MEDIUMBLOB', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
    }

    #[Test]
    public function generateLongBlob(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'LONGBLOB', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
    }

    #[Test]
    public function generateEnumEmpty(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'ENUM', enumValues: [], nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertNull($value);
    }

    #[Test]
    public function generateSetEmpty(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'SET', enumValues: [], nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertNull($value);
    }

    #[Test]
    public function generateLineString(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'LINESTRING', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertStringStartsWith('LINESTRING(', $value);
    }

    #[Test]
    public function generatePolygon(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'POLYGON', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertStringStartsWith('POLYGON((', $value);
    }

    #[Test]
    public function generateMultiPoint(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'MULTIPOINT', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertStringStartsWith('MULTIPOINT(', $value);
    }

    #[Test]
    public function generateMultiLineString(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'MULTILINESTRING', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertStringStartsWith('MULTILINESTRING(', $value);
    }

    #[Test]
    public function generateMultiPolygon(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'MULTIPOLYGON', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertStringStartsWith('MULTIPOLYGON(', $value);
    }

    #[Test]
    public function generateGeometry(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'GEOMETRY', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertStringStartsWith('POINT(', $value);
    }

    #[Test]
    public function generateGeometryCollection(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'GEOMETRYCOLLECTION', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertStringStartsWith('GEOMETRYCOLLECTION(', $value);
    }

    #[Test]
    public function generateBoolean(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'BOOLEAN', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsBool($value);
    }

    #[Test]
    public function generateBool(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'BOOL', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsBool($value);
    }

    #[Test]
    public function generateReal(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'REAL', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsFloat($value);
    }

    #[Test]
    public function generateNumeric(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'NUMERIC', precision: 8, scale: 2, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsFloat($value);
    }

    #[Test]
    public function generateInteger(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'INTEGER', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsInt($value);
    }

    #[Test]
    public function generateUnknownType(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'UNKNOWN_TYPE', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
    }

    #[Test]
    public function generateNullableWithDefaultNull(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'INT', nullable: true, default: null);

        [$hasNull, $hasValue] = (static function () use ($faker, $mapper, $column): array {
            $hasNull = false;
            $hasValue = false;
            for ($i = 0; $i < 100; $i++) {
                $value = $mapper->generate($faker, $column);
                if ($value === null) {
                    $hasNull = true;
                } else {
                    $hasValue = true;
                    self::assertIsInt($value);
                }
                if ($hasNull && $hasValue) {
                    break;
                }
            }

            return [$hasNull, $hasValue];
        })();

        self::assertTrue($hasNull, 'Expected at least one null value in 100 iterations');
        self::assertTrue($hasValue, 'Expected at least one non-null value in 100 iterations');
    }

    #[Test]
    public function generateNonNullableNeverReturnsNull(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'INT', nullable: false);

        (static function () use ($faker, $mapper, $column): void {
            for ($i = 0; $i < 50; $i++) {
                $value = $mapper->generate($faker, $column);
                self::assertNotNull($value, 'Non-nullable column should never return null');
                self::assertIsInt($value);
            }
        })();
    }

    #[Test]
    public function generateDecimalBoundaryValues(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'DECIMAL', precision: 5, scale: 2, nullable: false);

        (static function () use ($faker, $mapper, $column): void {
            for ($i = 0; $i < 50; $i++) {
                $value = $mapper->generate($faker, $column);
                self::assertIsFloat($value);
                self::assertGreaterThanOrEqual(-999.99, $value);
                self::assertLessThanOrEqual(999.99, $value);
            }
        })();
    }

    #[Test]
    public function generateIntBoundaryValues(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'INT', nullable: false);

        (static function () use ($faker, $mapper, $column): void {
            for ($i = 0; $i < 50; $i++) {
                $value = $mapper->generate($faker, $column);
                self::assertIsInt($value);
                self::assertGreaterThanOrEqual(-2147483648, $value);
                self::assertLessThanOrEqual(2147483647, $value);
            }
        })();
    }

    #[Test]
    public function generateIntUnsignedBoundaryValues(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'INT', unsigned: true, nullable: false);

        (static function () use ($faker, $mapper, $column): void {
            for ($i = 0; $i < 50; $i++) {
                $value = $mapper->generate($faker, $column);
                self::assertIsInt($value);
                self::assertGreaterThanOrEqual(0, $value);
                self::assertLessThanOrEqual(4294967295, $value);
            }
        })();
    }

    #[Test]
    public function generateVarcharRespectsLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'VARCHAR', length: 10, nullable: false);

        (static function () use ($faker, $mapper, $column): void {
            for ($i = 0; $i < 20; $i++) {
                $value = $mapper->generate($faker, $column);
                self::assertIsString($value);
                self::assertLessThanOrEqual(10, strlen($value));
                self::assertGreaterThan(0, strlen($value));
            }
        })();
    }

    #[Test]
    public function generateBitBoundaryValues(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column1 = new ColumnDefinition('col', 'BIT', length: 1, nullable: false);
        $value = $mapper->generate($faker, $column1);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(0, $value);
        self::assertLessThanOrEqual(1, $value);

        $column16 = new ColumnDefinition('col', 'BIT', length: 16, nullable: false);
        (static function () use ($faker, $mapper, $column16): void {
            for ($i = 0; $i < 20; $i++) {
                $value = $mapper->generate($faker, $column16);
                self::assertIsInt($value);
                self::assertGreaterThanOrEqual(0, $value);
                self::assertLessThanOrEqual(65535, $value);
            }
        })();
    }

    #[Test]
    public function generateYearBoundary(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'YEAR', nullable: false);

        (static function () use ($faker, $mapper, $column): void {
            for ($i = 0; $i < 50; $i++) {
                $value = $mapper->generate($faker, $column);
                self::assertIsInt($value);
                self::assertGreaterThanOrEqual(1901, $value);
                self::assertLessThanOrEqual(2155, $value);
            }
        })();
    }
}
