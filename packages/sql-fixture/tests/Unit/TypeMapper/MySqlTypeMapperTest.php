<?php

declare(strict_types=1);

namespace Tests\Unit\TypeMapper;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\MySqlTypeMapper as PlatformMySqlTypeMapper;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\TypeMapper\MySqlTypeMapper;

#[CoversClass(MySqlTypeMapper::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(PlatformMySqlTypeMapper::class)]
#[UsesClass(\SqlFixture\TypeMapper\TypeMapperInterface::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Value\ColumnGenerator::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Value\DecimalGenerator::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Value\GeometryGenerator::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Value\IntegerGenerator::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Value\NumericGenerator::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Value\SpatialGenerator::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Value\StringGenerator::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Value\TextGenerator::class)]
#[UsesClass(\SqlFixture\TypeMapper\ParagraphGenerator::class)]
final class MySqlTypeMapperTest extends TestCase
{
    #[Test]
    public function testGenerateTinyInt(): void
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
    public function testGenerateTinyIntUnsigned(): void
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
    public function testGenerateTinyIntOneAsBoolean(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'TINYINT', length: 1, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsBool($value);
    }

    #[Test]
    public function testGenerateInt(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'INT', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsInt($value);
    }

    #[Test]
    public function testGenerateDecimal(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'DECIMAL', precision: 10, scale: 2, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsFloat($value);
    }

    #[Test]
    public function testGenerateVarchar(): void
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
    public function testGenerateChar(): void
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
    public function testGenerateDate(): void
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
    public function testGenerateTime(): void
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
    public function testGenerateDatetime(): void
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
    public function testGenerateTimestamp(): void
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
    public function testGenerateYear(): void
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
    public function testGenerateEnum(): void
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
    public function testGenerateSet(): void
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
    public function testGenerateJson(): void
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
    public function testGeneratePoint(): void
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
    public function testGenerateAutoIncrementReturnsNull(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('id', 'INT', autoIncrement: true, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertNull($value);
    }

    #[Test]
    public function testGenerateGeneratedColumnReturnsNull(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('computed', 'INT', generated: true, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertNull($value);
    }

    #[Test]
    public function testGenerateBit(): void
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
    public function testGenerateBinary(): void
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
    public function testGenerateBlob(): void
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
    public function testGenerateText(): void
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
    public function testGenerateSmallInt(): void
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
    public function testGenerateSmallIntUnsigned(): void
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
    public function testGenerateMediumInt(): void
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
    public function testGenerateMediumIntUnsigned(): void
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
    public function testGenerateIntUnsigned(): void
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
    public function testGenerateBigInt(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'BIGINT', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsInt($value);
    }

    #[Test]
    public function testGenerateBigIntUnsigned(): void
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
    public function testGenerateFloat(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'FLOAT', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsFloat($value);
    }

    #[Test]
    public function testGenerateDouble(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'DOUBLE', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsFloat($value);
    }

    #[Test]
    public function testGenerateDecimalUnsigned(): void
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
    public function testGenerateTinyText(): void
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
    public function testGenerateMediumText(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'MEDIUMTEXT', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
    }

    #[Test]
    public function testGenerateLongText(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'LONGTEXT', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
    }

    #[Test]
    public function testGenerateVarbinary(): void
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
    public function testGenerateTinyBlob(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'TINYBLOB', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
    }

    #[Test]
    public function testGenerateMediumBlob(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'MEDIUMBLOB', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
    }

    #[Test]
    public function testGenerateLongBlob(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'LONGBLOB', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
    }

    #[Test]
    public function testGenerateEnumEmpty(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'ENUM', enumValues: [], nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertNull($value);
    }

    #[Test]
    public function testGenerateSetEmpty(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'SET', enumValues: [], nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertNull($value);
    }

    #[Test]
    public function testGenerateLineString(): void
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
    public function testGeneratePolygon(): void
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
    public function testGenerateMultiPoint(): void
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
    public function testGenerateMultiLineString(): void
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
    public function testGenerateMultiPolygon(): void
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
    public function testGenerateGeometry(): void
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
    public function testGenerateGeometryCollection(): void
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
    public function testGenerateBoolean(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'BOOLEAN', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsBool($value);
    }

    #[Test]
    public function testGenerateBool(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'BOOL', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsBool($value);
    }

    #[Test]
    public function testGenerateReal(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'REAL', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsFloat($value);
    }

    #[Test]
    public function testGenerateNumeric(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'NUMERIC', precision: 8, scale: 2, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsFloat($value);
    }

    #[Test]
    public function testGenerateInteger(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'INTEGER', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsInt($value);
    }

    #[Test]
    public function testGenerateUnknownType(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'UNKNOWN_TYPE', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
    }

    #[Test]
    public function testGenerateNullableWithDefaultNull(): void
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
    public function testGenerateNonNullableNeverReturnsNull(): void
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
    public function testGenerateDecimalBoundaryValues(): void
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
    public function testGenerateIntBoundaryValues(): void
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
    public function testGenerateIntUnsignedBoundaryValues(): void
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
    public function testGenerateVarcharRespectsLength(): void
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
    public function testGenerateBitBoundaryValues(): void
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
    public function testGenerateYearBoundary(): void
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
