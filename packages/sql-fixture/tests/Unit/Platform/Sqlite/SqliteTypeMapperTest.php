<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Sqlite;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\Sqlite\SqliteTypeMapper;
use SqlFixture\Schema\ColumnDefinition;
use Tests\Fixture\SpyGenerator;

#[CoversClass(SqliteTypeMapper::class)]
#[UsesClass(ColumnDefinition::class)]
#[CoversClass(\SqlFixture\Platform\Sqlite\Value\ColumnGenerator::class)]
#[CoversClass(\SqlFixture\Platform\Sqlite\Value\TypeAffinity::class)]
#[UsesClass(\SqlFixture\TypeMapper\ParagraphGenerator::class)]
#[UsesClass(\SqlFixture\TypeMapper\TypeMapperInterface::class)]
final class SqliteTypeMapperTest extends TestCase
{
    #[Test]
    public function testGenerateIntegerAffinity(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'INTEGER', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(-2147483648, $value);
        self::assertLessThanOrEqual(2147483647, $value);
    }

    #[Test]
    public function testGenerateInt(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'INT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(-2147483648, $value);
        self::assertLessThanOrEqual(2147483647, $value);
    }

    #[Test]
    public function testGenerateTinyInt(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'TINYINT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(-128, $value);
        self::assertLessThanOrEqual(127, $value);
    }

    #[Test]
    public function testGenerateSmallInt(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'SMALLINT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(-32768, $value);
        self::assertLessThanOrEqual(32767, $value);
    }

    #[Test]
    public function testGenerateBigInt(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'BIGINT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
    }

    #[Test]
    public function testGenerateTextAffinity(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'TEXT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertGreaterThan(0, strlen($value));
    }

    #[Test]
    public function testGenerateVarchar(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'VARCHAR', length: 100, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertSame(100, strlen($value));
    }

    #[Test]
    public function testGenerateChar(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'CHAR', length: 5, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertSame(5, strlen($value));
    }

    #[Test]
    public function testGenerateRealAffinity(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'REAL', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-1000000.0, $value);
        self::assertLessThanOrEqual(1000000.0, $value);
    }

    #[Test]
    public function testGenerateFloat(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'FLOAT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-1000.0, $value);
        self::assertLessThanOrEqual(1000.0, $value);
    }

    #[Test]
    public function testGenerateDouble(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'DOUBLE', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-1000000.0, $value);
        self::assertLessThanOrEqual(1000000.0, $value);
    }

    #[Test]
    public function testGenerateBlobAffinity(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'BLOB', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertGreaterThanOrEqual(1, strlen($value));
        self::assertLessThanOrEqual(1000, strlen($value));
    }

    #[Test]
    public function testGenerateBlobWithLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'BLOB', length: 16, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertSame(16, strlen($value));
    }

    #[Test]
    public function testGenerateNumericAffinityBoolean(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'BOOLEAN', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertContains($value, [0, 1]);
    }

    #[Test]
    public function testGenerateNumericAffinityDate(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'DATE', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $value);
    }

    #[Test]
    public function testGenerateNumericAffinityTime(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'TIME', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', $value);
    }

    #[Test]
    public function testGenerateNumericAffinityDatetime(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'DATETIME', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value);
    }

    #[Test]
    public function testGenerateDecimal(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'DECIMAL', precision: 10, scale: 2, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-99999999.0, $value);
        self::assertLessThanOrEqual(99999999.0, $value);
    }

    #[Test]
    public function testGenerateAutoIncrementReturnsNull(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('id', 'INTEGER', autoIncrement: true, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertNull($value);
    }

    #[Test]
    public function testGenerateGeneratedColumnReturnsNull(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('computed', 'INTEGER', generated: true, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertNull($value);
    }

    #[Test]
    public function testGenerateNullable(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'INTEGER', nullable: true, default: null);

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
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'INTEGER', nullable: false);

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
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'DECIMAL', precision: 5, scale: 2, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-999.0, $value);
        self::assertLessThanOrEqual(999.0, $value);
    }

    #[Test]
    public function testGenerateIntegerBoundaryValues(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'INTEGER', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(-2147483648, $value);
        self::assertLessThanOrEqual(2147483647, $value);
    }

    #[Test]
    public function testGenerateTinyText(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'TINYTEXT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertGreaterThan(0, strlen($value));
        self::assertLessThanOrEqual(255, strlen($value));
    }

    #[Test]
    public function testGenerateMediumText(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'MEDIUMTEXT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertGreaterThan(0, strlen($value));
    }

    #[Test]
    public function testGenerateClob(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'CLOB', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertGreaterThan(0, strlen($value));
    }

    #[Test]
    public function testGenerateTimestamp(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'TIMESTAMP', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value);
    }

    #[Test]
    public function testGenerateNumeric(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'NUMERIC', precision: 8, scale: 2, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-999999.0, $value);
        self::assertLessThanOrEqual(999999.0, $value);
    }

    #[Test]
    public function testGenerateMediumInt(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'MEDIUMINT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(-8388608, $value);
        self::assertLessThanOrEqual(8388607, $value);
    }

    #[Test]
    public function testGenerateInt2Alias(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'INT2', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(-32768, $value);
        self::assertLessThanOrEqual(32767, $value);
    }

    #[Test]
    public function testGenerateInt8Alias(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'INT8', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
    }

    #[Test]
    public function testGenerateLongText(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'LONGTEXT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertGreaterThan(0, strlen($value));
    }

    #[Test]
    public function testGenerateTextWithLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'VARCHAR', length: 50, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertSame(50, strlen($value));
    }

    #[Test]
    public function testGenerateRealWithPrecisionScale(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'REAL', precision: 5, scale: 2, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-999.0, $value);
        self::assertLessThanOrEqual(999.0, $value);
    }

    #[Test]
    public function testGenerateFloatRange(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'FLOAT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-1000.0, $value);
        self::assertLessThanOrEqual(1000.0, $value);
    }

    #[Test]
    public function testGenerateDecimalDefaultPrecision(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'DECIMAL', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-9999999999.0, $value);
        self::assertLessThanOrEqual(9999999999.0, $value);
    }

    #[Test]
    public function testGenerateNumericDefaultAffinity(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'ANYTYPE', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-1000.0, $value);
        self::assertLessThanOrEqual(1000.0, $value);
    }

    #[Test]
    public function testGenerateEmptyTypeBlobAffinity(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', '', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertGreaterThanOrEqual(1, strlen($value));
        self::assertLessThanOrEqual(1000, strlen($value));
    }

    #[Test]
    public function testGenerateLowercaseTypeWorks(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'integer', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(-2147483648, $value);
        self::assertLessThanOrEqual(2147483647, $value);
    }

    #[Test]
    public function testGenerateSmallIntBoundary(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'SMALLINT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(-32768, $value);
        self::assertLessThanOrEqual(32767, $value);
    }

    #[Test]
    public function testSnapshotInteger(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'INTEGER', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(-2147483648, $value);
        self::assertLessThanOrEqual(2147483647, $value);
    }

    #[Test]
    public function testSnapshotInt(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'INT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(-2147483648, $value);
        self::assertLessThanOrEqual(2147483647, $value);
    }

    #[Test]
    public function testSnapshotTinyInt(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'TINYINT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(-128, $value);
        self::assertLessThanOrEqual(127, $value);
    }

    #[Test]
    public function testSnapshotSmallInt(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'SMALLINT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(-32768, $value);
        self::assertLessThanOrEqual(32767, $value);
    }

    #[Test]
    public function testSnapshotInt2(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'INT2', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(-32768, $value);
        self::assertLessThanOrEqual(32767, $value);
    }

    #[Test]
    public function testSnapshotMediumInt(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'MEDIUMINT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(-8388608, $value);
        self::assertLessThanOrEqual(8388607, $value);
    }

    #[Test]
    public function testSnapshotBigInt(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'BIGINT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
    }

    #[Test]
    public function testSnapshotInt8(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'INT8', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
    }

    #[Test]
    public function testSnapshotChar(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'CHAR', length: 5, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertSame(5, strlen($value));
    }

    #[Test]
    public function testSnapshotReal(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'REAL', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-1000000.0, $value);
        self::assertLessThanOrEqual(1000000.0, $value);
    }

    #[Test]
    public function testSnapshotFloatValue(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'FLOAT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-1000.0, $value);
        self::assertLessThanOrEqual(1000.0, $value);
    }

    #[Test]
    public function testSnapshotDouble(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'DOUBLE', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-1000000.0, $value);
        self::assertLessThanOrEqual(1000000.0, $value);
    }

    #[Test]
    public function testSnapshotRealWithPrecision(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'REAL', precision: 5, scale: 2, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-999.0, $value);
        self::assertLessThanOrEqual(999.0, $value);
    }

    #[Test]
    public function testSnapshotBoolean(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'BOOLEAN', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertContains($value, [0, 1]);
    }

    #[Test]
    public function testSnapshotDate(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'DATE', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $value);
    }

    #[Test]
    public function testSnapshotTime(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'TIME', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', $value);
    }

    #[Test]
    public function testSnapshotDatetime(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'DATETIME', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value);
    }

    #[Test]
    public function testSnapshotTimestamp(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'TIMESTAMP', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value);
    }

    #[Test]
    public function testSnapshotDecimal(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'DECIMAL', precision: 5, scale: 2, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-999.0, $value);
        self::assertLessThanOrEqual(999.0, $value);
    }

    #[Test]
    public function testSnapshotNumeric(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'NUMERIC', precision: 8, scale: 2, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-999999.0, $value);
        self::assertLessThanOrEqual(999999.0, $value);
    }

    #[Test]
    public function testSnapshotAnytype(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'ANYTYPE', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-1000.0, $value);
        self::assertLessThanOrEqual(1000.0, $value);
    }

    #[Test]
    public function testSnapshotBlobWithLength(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'BLOB', length: 16, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertSame(16, strlen($value));
    }

    #[Test]
    public function testSnapshotBlobWithoutLength(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'BLOB', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertGreaterThanOrEqual(1, strlen($value));
        self::assertLessThanOrEqual(1000, strlen($value));
    }

    #[Test]
    public function testSnapshotEmptyType(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', '', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertGreaterThanOrEqual(1, strlen($value));
        self::assertLessThanOrEqual(1000, strlen($value));
    }

    #[Test]
    public function testSnapshotDecimalDefaultPrecision(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'DECIMAL', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-9999999999.0, $value);
        self::assertLessThanOrEqual(9999999999.0, $value);
    }

    #[Test]
    public function testSnapshotBooleanValues(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'BOOLEAN', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertContains($value, [0, 1]);

        $faker->seed(1);
        $value = $mapper->generate($faker, $column);
        self::assertContains($value, [0, 1]);
    }

    #[Test]
    public function testSnapshotBlobExactLength(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'BLOB', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertGreaterThanOrEqual(1, strlen($value));
        self::assertLessThanOrEqual(1000, strlen($value));
    }

    #[Test]
    public function testSnapshotAnytypeExact(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'ANYTYPE', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-1000.0, $value);
        self::assertLessThanOrEqual(1000.0, $value);
    }

    #[Test]
    public function testSnapshotFloatExact(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'FLOAT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-1000.0, $value);
        self::assertLessThanOrEqual(1000.0, $value);
    }

    #[Test]
    public function testSnapshotDecimalExact(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'DECIMAL', precision: 5, scale: 2, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-999.0, $value);
        self::assertLessThanOrEqual(999.0, $value);
    }

    #[Test]
    public function testLowercaseTypeUsesStrtoupper(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();

        $upperCol = new ColumnDefinition('col', 'BOOLEAN', nullable: false);
        $lowerCol = new ColumnDefinition('col', 'boolean', nullable: false);

        $faker->seed(42);
        $upperVal = $mapper->generate($faker, $upperCol);
        $faker->seed(42);
        $lowerVal = $mapper->generate($faker, $lowerCol);

        self::assertSame($upperVal, $lowerVal);
    }

    #[Test]
    public function testLowercaseTinyintGeneratesSameAsUppercase(): void
    {
        $faker = Factory::create();
        $mapper = new SqliteTypeMapper();

        $faker->seed(42);
        $upper = $mapper->generate($faker, new ColumnDefinition('col', 'TINYINT', nullable: false));
        $faker->seed(42);
        $lower = $mapper->generate($faker, new ColumnDefinition('col', 'tinyint', nullable: false));

        self::assertSame($upper, $lower);
    }

    #[Test]
    public function testLowercaseSmallintGeneratesSameAsUppercase(): void
    {
        $faker = Factory::create();
        $mapper = new SqliteTypeMapper();

        $faker->seed(42);
        $upper = $mapper->generate($faker, new ColumnDefinition('col', 'SMALLINT', nullable: false));
        $faker->seed(42);
        $lower = $mapper->generate($faker, new ColumnDefinition('col', 'smallint', nullable: false));

        self::assertSame($upper, $lower);
    }

    #[Test]
    public function testLowercaseInt2GeneratesSameAsUppercase(): void
    {
        $faker = Factory::create();
        $mapper = new SqliteTypeMapper();

        $faker->seed(42);
        $upper = $mapper->generate($faker, new ColumnDefinition('col', 'INT2', nullable: false));
        $faker->seed(42);
        $lower = $mapper->generate($faker, new ColumnDefinition('col', 'int2', nullable: false));

        self::assertSame($upper, $lower);
    }

    #[Test]
    public function testLowercaseMediumintGeneratesSameAsUppercase(): void
    {
        $faker = Factory::create();
        $mapper = new SqliteTypeMapper();

        $faker->seed(42);
        $upper = $mapper->generate($faker, new ColumnDefinition('col', 'MEDIUMINT', nullable: false));
        $faker->seed(42);
        $lower = $mapper->generate($faker, new ColumnDefinition('col', 'mediumint', nullable: false));

        self::assertSame($upper, $lower);
    }

    #[Test]
    public function testLowercaseBigintGeneratesSameAsUppercase(): void
    {
        $faker = Factory::create();
        $mapper = new SqliteTypeMapper();

        $faker->seed(42);
        $upper = $mapper->generate($faker, new ColumnDefinition('col', 'BIGINT', nullable: false));
        $faker->seed(42);
        $lower = $mapper->generate($faker, new ColumnDefinition('col', 'bigint', nullable: false));

        self::assertSame($upper, $lower);
    }

    #[Test]
    public function testLowercaseInt8GeneratesSameAsUppercase(): void
    {
        $faker = Factory::create();
        $mapper = new SqliteTypeMapper();

        $faker->seed(42);
        $upper = $mapper->generate($faker, new ColumnDefinition('col', 'INT8', nullable: false));
        $faker->seed(42);
        $lower = $mapper->generate($faker, new ColumnDefinition('col', 'int8', nullable: false));

        self::assertSame($upper, $lower);
    }

    #[Test]
    public function testLowercaseCharGeneratesSameAsUppercase(): void
    {
        $faker = Factory::create();
        $mapper = new SqliteTypeMapper();

        $faker->seed(42);
        $upper = $mapper->generate($faker, new ColumnDefinition('col', 'CHAR', length: 10, nullable: false));
        $faker->seed(42);
        $lower = $mapper->generate($faker, new ColumnDefinition('col', 'char', length: 10, nullable: false));

        self::assertSame($upper, $lower);
    }

    #[Test]
    public function testLowercaseTinytextGeneratesSameAsUppercase(): void
    {
        $faker = Factory::create();
        $mapper = new SqliteTypeMapper();

        $faker->seed(42);
        $upper = $mapper->generate($faker, new ColumnDefinition('col', 'TINYTEXT', nullable: false));
        $faker->seed(42);
        $lower = $mapper->generate($faker, new ColumnDefinition('col', 'tinytext', nullable: false));

        self::assertSame($upper, $lower);
    }

    #[Test]
    public function testLowercaseMediumtextGeneratesSameAsUppercase(): void
    {
        $faker = Factory::create();
        $mapper = new SqliteTypeMapper();

        $faker->seed(42);
        $upper = $mapper->generate($faker, new ColumnDefinition('col', 'MEDIUMTEXT', nullable: false));
        $faker->seed(42);
        $lower = $mapper->generate($faker, new ColumnDefinition('col', 'mediumtext', nullable: false));

        self::assertSame($upper, $lower);
    }

    #[Test]
    public function testLowercaseLongtextGeneratesSameAsUppercase(): void
    {
        $faker = Factory::create();
        $mapper = new SqliteTypeMapper();

        $faker->seed(42);
        $upper = $mapper->generate($faker, new ColumnDefinition('col', 'LONGTEXT', nullable: false));
        $faker->seed(42);
        $lower = $mapper->generate($faker, new ColumnDefinition('col', 'longtext', nullable: false));

        self::assertSame($upper, $lower);
    }

    #[Test]
    public function testLowercaseClobGeneratesSameAsUppercase(): void
    {
        $faker = Factory::create();
        $mapper = new SqliteTypeMapper();

        $faker->seed(42);
        $upper = $mapper->generate($faker, new ColumnDefinition('col', 'CLOB', nullable: false));
        $faker->seed(42);
        $lower = $mapper->generate($faker, new ColumnDefinition('col', 'clob', nullable: false));

        self::assertSame($upper, $lower);
    }

    #[Test]
    public function testLowercaseFloatGeneratesSameAsUppercase(): void
    {
        $faker = Factory::create();
        $mapper = new SqliteTypeMapper();

        $faker->seed(42);
        $upper = $mapper->generate($faker, new ColumnDefinition('col', 'FLOAT', nullable: false));
        $faker->seed(42);
        $lower = $mapper->generate($faker, new ColumnDefinition('col', 'float', nullable: false));

        self::assertSame($upper, $lower);
    }

    #[Test]
    public function testLowercaseDoubleGeneratesSameAsUppercase(): void
    {
        $faker = Factory::create();
        $mapper = new SqliteTypeMapper();

        $faker->seed(42);
        $upper = $mapper->generate($faker, new ColumnDefinition('col', 'DOUBLE', nullable: false));
        $faker->seed(42);
        $lower = $mapper->generate($faker, new ColumnDefinition('col', 'double', nullable: false));

        self::assertSame($upper, $lower);
    }

    #[Test]
    public function testLowercaseDecimalGeneratesSameAsUppercase(): void
    {
        $faker = Factory::create();
        $mapper = new SqliteTypeMapper();

        $faker->seed(42);
        $upper = $mapper->generate($faker, new ColumnDefinition('col', 'DECIMAL', nullable: false));
        $faker->seed(42);
        $lower = $mapper->generate($faker, new ColumnDefinition('col', 'decimal', nullable: false));

        self::assertSame($upper, $lower);
    }

    #[Test]
    public function testLowercaseDateGeneratesSameAsUppercase(): void
    {
        $faker = Factory::create();
        $mapper = new SqliteTypeMapper();

        $faker->seed(42);
        $upper = $mapper->generate($faker, new ColumnDefinition('col', 'DATE', nullable: false));
        $faker->seed(42);
        $lower = $mapper->generate($faker, new ColumnDefinition('col', 'date', nullable: false));

        self::assertSame($upper, $lower);
    }

    #[Test]
    public function testLowercaseTimeGeneratesSameAsUppercase(): void
    {
        $faker = Factory::create();
        $mapper = new SqliteTypeMapper();

        $faker->seed(42);
        $upper = $mapper->generate($faker, new ColumnDefinition('col', 'TIME', nullable: false));
        $faker->seed(42);
        $lower = $mapper->generate($faker, new ColumnDefinition('col', 'time', nullable: false));

        self::assertSame($upper, $lower);
    }

    #[Test]
    public function testLowercaseDatetimeGeneratesSameAsUppercase(): void
    {
        $faker = Factory::create();
        $mapper = new SqliteTypeMapper();

        $faker->seed(42);
        $upper = $mapper->generate($faker, new ColumnDefinition('col', 'DATETIME', nullable: false));
        $faker->seed(42);
        $lower = $mapper->generate($faker, new ColumnDefinition('col', 'datetime', nullable: false));

        self::assertSame($upper, $lower);
    }

    #[Test]
    public function testLowercaseTimestampGeneratesSameAsUppercase(): void
    {
        $faker = Factory::create();
        $mapper = new SqliteTypeMapper();

        $faker->seed(42);
        $upper = $mapper->generate($faker, new ColumnDefinition('col', 'TIMESTAMP', nullable: false));
        $faker->seed(42);
        $lower = $mapper->generate($faker, new ColumnDefinition('col', 'timestamp', nullable: false));

        self::assertSame($upper, $lower);
    }

    #[Test]
    public function testNullableColumnReturnsDefault(): void
    {
        $faker = Factory::create();
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'INTEGER', nullable: true, default: 42);

        [$gotDefault, $gotGenerated] = (static function () use ($faker, $mapper, $column): array {
            $gotDefault = false;
            $gotGenerated = false;
            for ($i = 0; $i < 200; $i++) {
                $faker->seed($i);
                $value = $mapper->generate($faker, $column);
                if ($value === 42) {
                    $gotDefault = true;
                } elseif (is_int($value)) {
                    $gotGenerated = true;
                }
                if ($gotDefault && $gotGenerated) {
                    break;
                }
            }

            return [$gotDefault, $gotGenerated];
        })();
        self::assertTrue($gotDefault, 'Nullable column should sometimes return default');
        self::assertTrue($gotGenerated, 'Nullable column should sometimes return generated value');
    }

    #[Test]
    public function testNullableColumnReturnsDefaultWithSpecificSeed(): void
    {
        $faker = Factory::create();
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'INTEGER', nullable: true, default: 'MARKER');

        $foundDefault = (static function () use ($faker, $mapper, $column): bool {
            for ($i = 0; $i < 200; $i++) {
                $faker->seed($i);
                $value = $mapper->generate($faker, $column);
                if ($value === 'MARKER') {
                    return true;
                }
            }

            return false;
        })();
        self::assertTrue($foundDefault, 'Expected at least one seed to return the default value');
    }

    #[Test]
    public function testNullableColumnReturnsGeneratedWithSpecificSeed(): void
    {
        $faker = Factory::create();
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'INTEGER', nullable: true, default: 'MARKER');

        $foundGenerated = (static function () use ($faker, $mapper, $column): bool {
            for ($i = 0; $i < 200; $i++) {
                $faker->seed($i);
                $value = $mapper->generate($faker, $column);
                if (is_int($value)) {
                    return true;
                }
            }

            return false;
        })();
        self::assertTrue($foundGenerated, 'Expected at least one seed to return a generated int value');
    }

    #[Test]
    public function testNullableColumnSeed28ReturnsDefault(): void
    {
        $faker = Factory::create();
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'INTEGER', nullable: true, default: 'MARKER');

        $foundDefault = (static function () use ($faker, $mapper, $column): bool {
            for ($i = 0; $i < 200; $i++) {
                $faker->seed($i);
                $value = $mapper->generate($faker, $column);
                if ($value === 'MARKER') {
                    return true;
                }
            }

            return false;
        })();
        self::assertTrue($foundDefault, 'Expected at least one seed to return the default value');
    }

    #[Test]
    public function testNullableColumnSeed285ReturnsGenerated(): void
    {
        $faker = Factory::create();
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'INTEGER', nullable: true, default: 'MARKER');

        $foundGenerated = (static function () use ($faker, $mapper, $column): bool {
            for ($i = 0; $i < 500; $i++) {
                $faker->seed($i);
                $value = $mapper->generate($faker, $column);
                if (is_int($value)) {
                    return true;
                }
            }

            return false;
        })();
        self::assertTrue($foundGenerated, 'Expected at least one seed to return a generated int value');
    }

    #[Test]
    public function testGenerateTextWithLengthSeed12345(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'TEXT', length: 50, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertLessThanOrEqual(50, strlen($value));
        self::assertGreaterThan(0, strlen($value));
    }

    #[Test]
    public function testGenerateDoublePrecisionOnlySeed12345(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'DOUBLE', precision: 10, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-1000000.0, $value);
        self::assertLessThanOrEqual(1000000.0, $value);
    }

    #[Test]
    public function testSpyTinyIntBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'TINYINT', nullable: false));
        self::assertSame([-128, 127], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpySmallIntBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'SMALLINT', nullable: false));
        self::assertSame([-32768, 32767], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyInt2Boundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INT2', nullable: false));
        self::assertSame([-32768, 32767], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyMediumIntBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'MEDIUMINT', nullable: false));
        self::assertSame([-8388608, 8388607], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyIntegerDefaultBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INTEGER', nullable: false));
        self::assertSame([-2147483648, 2147483647], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyBigIntBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'BIGINT', nullable: false));
        self::assertSame([PHP_INT_MIN, PHP_INT_MAX], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyInt8Boundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INT8', nullable: false));
        self::assertSame([PHP_INT_MIN, PHP_INT_MAX], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyFloatBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'FLOAT', nullable: false));
        self::assertSame([2, -1000.0, 1000.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function testSpyDoubleDefaultBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'DOUBLE', nullable: false));
        self::assertSame([4, -1000000.0, 1000000.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function testSpyRealDefaultBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'REAL', nullable: false));
        self::assertSame([4, -1000000.0, 1000000.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function testSpyRealWithPrecisionScale(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'REAL', precision: 5, scale: 2, nullable: false));
        self::assertSame([2, -999.0, 999.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function testSpyDecimalBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'DECIMAL', precision: 5, scale: 2, nullable: false));
        self::assertSame([2, -999.0, 999.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function testSpyDecimalDefaultPrecision(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'DECIMAL', nullable: false));
        self::assertSame([0, -9999999999.0, 9999999999.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function testSpyNumericBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'NUMERIC', precision: 8, scale: 3, nullable: false));
        self::assertSame([3, -99999.0, 99999.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function testSpyBooleanCallsBoolean(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'BOOLEAN', nullable: false));
        self::assertSame([50], $spy->booleanCalls[0]);
    }

    #[Test]
    public function testBooleanProducesBothZeroAndOne(): void
    {
        $faker = Factory::create();
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'BOOLEAN', nullable: false);

        [$gotZero, $gotOne] = (static function () use ($faker, $mapper, $column): array {
            $gotZero = false;
            $gotOne = false;
            for ($i = 0; $i < 200; $i++) {
                $faker->seed($i);
                $value = $mapper->generate($faker, $column);
                if ($value === 0) {
                    $gotZero = true;
                } elseif ($value === 1) {
                    $gotOne = true;
                }
                if ($gotZero && $gotOne) {
                    break;
                }
            }

            return [$gotZero, $gotOne];
        })();
        self::assertTrue($gotZero, 'Boolean column should sometimes produce 0');
        self::assertTrue($gotOne, 'Boolean column should sometimes produce 1');
    }

    #[Test]
    public function testSpyBlobWithoutLengthBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'BLOB', nullable: false));
        self::assertSame([1, 1000], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyTinyTextCallsText255(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'TINYTEXT', nullable: false));
        self::assertSame([255], $spy->methodCalls['text'][0]);
    }

    #[Test]
    public function testSpyTextCallsParagraphs2(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'TEXT', nullable: false));
        self::assertSame([2, true], $spy->methodCalls['paragraphs'][0]);
    }

    #[Test]
    public function testSpyMediumTextCallsParagraphs3(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'MEDIUMTEXT', nullable: false));
        self::assertSame([3, true], $spy->methodCalls['paragraphs'][0]);
    }

    #[Test]
    public function testSpyLongTextCallsParagraphs5(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'LONGTEXT', nullable: false));
        self::assertSame([5, true], $spy->methodCalls['paragraphs'][0]);
    }

    #[Test]
    public function testSpyClobCallsParagraphs5(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'CLOB', nullable: false));
        self::assertSame([5, true], $spy->methodCalls['paragraphs'][0]);
    }

    #[Test]
    public function testSpyCharLexifyPattern(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'CHAR', length: 5, nullable: false));
        self::assertSame(['?????'], $spy->methodCalls['lexify'][0]);
    }

    #[Test]
    public function testSpyVarcharLexifyPattern(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'VARCHAR', length: 3, nullable: false));
        self::assertSame(['???'], $spy->methodCalls['lexify'][0]);
    }

    #[Test]
    public function testSpyTextWithLengthBoundary(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'TEXT', length: 100, nullable: false));
        self::assertSame([100], $spy->methodCalls['text'][0]);
    }

    #[Test]
    public function testSpyTextWithLengthCapAt200(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'TEXT', length: 500, nullable: false));
        self::assertSame([200], $spy->methodCalls['text'][0]);
    }

    #[Test]
    public function testSpyNumericDefaultBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'ANYTYPE', nullable: false));
        self::assertSame([2, -1000.0, 1000.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function testSpyNullableCallsBooleanWithTen(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INTEGER', nullable: true));
        self::assertContains([10], $spy->booleanCalls);
    }

    #[Test]
    public function testGenerateTinyTextLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'TINYTEXT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertLessThanOrEqual(255, strlen($value));
    }

    #[Test]
    public function testGenerateTextWithLengthLimit(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'TEXT', length: 10, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertLessThanOrEqual(10, strlen($value));
    }

    #[Test]
    public function testGenerateTextWithLengthStartsFromBeginning(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'TEXT', length: 100, nullable: false);
        $value = $mapper->generate($faker, $column);

        $faker->seed(12345);
        $text = $faker->text(min(100, 200));
        $expected = substr($text, 0, 100);
        self::assertSame($expected, $value);
    }

    #[Test]
    public function testGenerateCharExactLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'CHAR', length: 5, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertSame(5, strlen($value));
    }

    #[Test]
    public function testGenerateBlobExactLengthOutput(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'BLOB', length: 8, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertSame(8, strlen($value));
    }

    #[Test]
    public function testGenerateBlobWithoutLengthNonEmpty(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'BLOB', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertGreaterThanOrEqual(1, strlen($value));
    }

    #[Test]
    public function testGenerateBooleanReturnsOneOrZero(): void
    {
        $faker = Factory::create();
        $mapper = new SqliteTypeMapper();

        $results = array_map(function (int $i) use ($faker, $mapper) {
            $faker->seed($i);
            $column = new ColumnDefinition('col', 'BOOLEAN', nullable: false);
            $value = $mapper->generate($faker, $column);
            self::assertContains($value, [0, 1]);

            return $value;
        }, range(0, 99));
        self::assertContains(0, $results);
        self::assertContains(1, $results);
    }

    #[Test]
    public function testNullableColumnDefaultRatioIsLow(): void
    {
        $faker = Factory::create();
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'INTEGER', nullable: true, default: 'MARKER');

        $total = 500;
        $defaultCount = count(array_filter(array_map(function (int $i) use ($faker, $mapper, $column) {
            $faker->seed($i);

            return $mapper->generate($faker, $column);
        }, range(0, $total - 1)), fn ($value): bool => $value === 'MARKER'));
        self::assertLessThan((int) ($total * 0.5), $defaultCount, 'Default should be returned rarely (10% chance), not often (90%)');
    }

    #[Test]
    public function testGenerateCharSubstrStartsAtZero(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'CHAR', length: 5, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);

        $faker->seed(42);
        $pattern = str_repeat('?', 5);
        $result = $faker->lexify($pattern);
        $expected = substr($result, 0, 5);
        self::assertSame($expected, $value);
    }

    #[Test]
    public function testGenerateTextWithLengthSubstrStartsAtZero(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'TEXT', length: 20, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);

        $faker->seed(42);
        $text = $faker->text(min(20, 200));
        $expected = substr($text, 0, 20);
        self::assertSame($expected, $value);
    }

    #[Test]
    public function testGenerateTinyTextStartsFromBeginning(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'TINYTEXT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);

        $faker->seed(42);
        $fullText = $faker->text(255);
        $expected = substr($fullText, 0, 255);
        self::assertSame($expected, $value);
    }
}
