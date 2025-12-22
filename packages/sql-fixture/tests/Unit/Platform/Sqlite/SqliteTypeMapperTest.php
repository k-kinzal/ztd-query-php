<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Sqlite;

use Faker\Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\Sqlite\SqliteTypeMapper;
use SqlFixture\Schema\ColumnDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Fixture\SpyGenerator;

#[CoversClass(SqliteTypeMapper::class)]
#[UsesClass(ColumnDefinition::class)]
final class SqliteTypeMapperTest extends TestCase
{
    #[Test]
    public function generateIntegerAffinity(): void
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
    public function generateInt(): void
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
    public function generateTinyInt(): void
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
    public function generateSmallInt(): void
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
    public function generateBigInt(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'BIGINT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
    }

    #[Test]
    public function generateTextAffinity(): void
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
    public function generateVarchar(): void
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
    public function generateChar(): void
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
    public function generateRealAffinity(): void
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
    public function generateFloat(): void
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
    public function generateDouble(): void
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
    public function generateBlobAffinity(): void
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
    public function generateBlobWithLength(): void
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
    public function generateNumericAffinityBoolean(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('col', 'BOOLEAN', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertContains($value, [0, 1]);
    }

    #[Test]
    public function generateNumericAffinityDate(): void
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
    public function generateNumericAffinityTime(): void
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
    public function generateNumericAffinityDatetime(): void
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
    public function generateDecimal(): void
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
    public function generateAutoIncrementReturnsNull(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('id', 'INTEGER', autoIncrement: true, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertNull($value);
    }

    #[Test]
    public function generateGeneratedColumnReturnsNull(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();

        $column = new ColumnDefinition('computed', 'INTEGER', generated: true, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertNull($value);
    }

    #[Test]
    public function generateNullable(): void
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
    public function generateNonNullableNeverReturnsNull(): void
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
    public function generateDecimalBoundaryValues(): void
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
    public function generateIntegerBoundaryValues(): void
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
    public function generateTinyText(): void
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
    public function generateMediumText(): void
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
    public function generateClob(): void
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
    public function generateTimestamp(): void
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
    public function generateNumeric(): void
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
    public function generateMediumInt(): void
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
    public function generateInt2Alias(): void
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
    public function generateInt8Alias(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'INT8', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
    }

    #[Test]
    public function generateLongText(): void
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
    public function generateTextWithLength(): void
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
    public function generateRealWithPrecisionScale(): void
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
    public function generateFloatRange(): void
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
    public function generateDecimalDefaultPrecision(): void
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
    public function generateNumericDefaultAffinity(): void
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
    public function generateEmptyTypeBlobAffinity(): void
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
    public function generateLowercaseTypeWorks(): void
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
    public function generateSmallIntBoundary(): void
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
    public function snapshotInteger(): void
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
    public function snapshotInt(): void
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
    public function snapshotTinyInt(): void
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
    public function snapshotSmallInt(): void
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
    public function snapshotInt2(): void
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
    public function snapshotMediumInt(): void
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
    public function snapshotBigInt(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'BIGINT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
    }

    #[Test]
    public function snapshotInt8(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'INT8', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
    }

    #[Test]
    public function snapshotChar(): void
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
    public function snapshotReal(): void
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
    public function snapshotFloatValue(): void
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
    public function snapshotDouble(): void
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
    public function snapshotRealWithPrecision(): void
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
    public function snapshotBoolean(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'BOOLEAN', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertContains($value, [0, 1]);
    }

    #[Test]
    public function snapshotDate(): void
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
    public function snapshotTime(): void
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
    public function snapshotDatetime(): void
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
    public function snapshotTimestamp(): void
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
    public function snapshotDecimal(): void
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
    public function snapshotNumeric(): void
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
    public function snapshotAnytype(): void
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
    public function snapshotBlobWithLength(): void
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
    public function snapshotBlobWithoutLength(): void
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
    public function snapshotEmptyType(): void
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
    public function snapshotDecimalDefaultPrecision(): void
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
    public function snapshotBooleanValues(): void
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
    public function snapshotBlobExactLength(): void
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
    public function snapshotAnytypeExact(): void
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
    public function snapshotFloatExact(): void
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
    public function snapshotDecimalExact(): void
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
    public function lowercaseTypeUsesStrtoupper(): void
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
    public function lowercaseTinyintGeneratesSameAsUppercase(): void
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
    public function lowercaseSmallintGeneratesSameAsUppercase(): void
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
    public function lowercaseInt2GeneratesSameAsUppercase(): void
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
    public function lowercaseMediumintGeneratesSameAsUppercase(): void
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
    public function lowercaseBigintGeneratesSameAsUppercase(): void
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
    public function lowercaseInt8GeneratesSameAsUppercase(): void
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
    public function lowercaseCharGeneratesSameAsUppercase(): void
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
    public function lowercaseTinytextGeneratesSameAsUppercase(): void
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
    public function lowercaseMediumtextGeneratesSameAsUppercase(): void
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
    public function lowercaseLongtextGeneratesSameAsUppercase(): void
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
    public function lowercaseClobGeneratesSameAsUppercase(): void
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
    public function lowercaseFloatGeneratesSameAsUppercase(): void
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
    public function lowercaseDoubleGeneratesSameAsUppercase(): void
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
    public function lowercaseDecimalGeneratesSameAsUppercase(): void
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
    public function lowercaseDateGeneratesSameAsUppercase(): void
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
    public function lowercaseTimeGeneratesSameAsUppercase(): void
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
    public function lowercaseDatetimeGeneratesSameAsUppercase(): void
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
    public function lowercaseTimestampGeneratesSameAsUppercase(): void
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
    public function nullableColumnReturnsDefault(): void
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
    public function nullableColumnReturnsDefaultWithSpecificSeed(): void
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
    public function nullableColumnReturnsGeneratedWithSpecificSeed(): void
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
    public function nullableColumnSeed28ReturnsDefault(): void
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
    public function nullableColumnSeed285ReturnsGenerated(): void
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
    public function generateTextWithLengthSeed12345(): void
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
    public function generateDoublePrecisionOnlySeed12345(): void
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
    public function spyTinyIntBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'TINYINT', nullable: false));
        self::assertSame([-128, 127], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spySmallIntBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'SMALLINT', nullable: false));
        self::assertSame([-32768, 32767], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyInt2Boundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INT2', nullable: false));
        self::assertSame([-32768, 32767], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyMediumIntBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'MEDIUMINT', nullable: false));
        self::assertSame([-8388608, 8388607], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyIntegerDefaultBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INTEGER', nullable: false));
        self::assertSame([-2147483648, 2147483647], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyBigIntBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'BIGINT', nullable: false));
        self::assertSame([PHP_INT_MIN, PHP_INT_MAX], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyInt8Boundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INT8', nullable: false));
        self::assertSame([PHP_INT_MIN, PHP_INT_MAX], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyFloatBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'FLOAT', nullable: false));
        self::assertSame([2, -1000.0, 1000.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function spyDoubleDefaultBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'DOUBLE', nullable: false));
        self::assertSame([4, -1000000.0, 1000000.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function spyRealDefaultBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'REAL', nullable: false));
        self::assertSame([4, -1000000.0, 1000000.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function spyRealWithPrecisionScale(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'REAL', precision: 5, scale: 2, nullable: false));
        self::assertSame([2, -999.0, 999.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function spyDecimalBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'DECIMAL', precision: 5, scale: 2, nullable: false));
        self::assertSame([2, -999.0, 999.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function spyDecimalDefaultPrecision(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'DECIMAL', nullable: false));
        self::assertSame([0, -9999999999.0, 9999999999.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function spyNumericBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'NUMERIC', precision: 8, scale: 3, nullable: false));
        self::assertSame([3, -99999.0, 99999.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function spyBooleanCallsBoolean(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'BOOLEAN', nullable: false));
        self::assertSame([50], $spy->booleanCalls[0]);
    }

    #[Test]
    public function booleanProducesBothZeroAndOne(): void
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
    public function spyBlobWithoutLengthBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'BLOB', nullable: false));
        self::assertSame([1, 1000], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyTinyTextCallsText255(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'TINYTEXT', nullable: false));
        self::assertSame([255], $spy->methodCalls['text'][0]);
    }

    #[Test]
    public function spyTextCallsParagraphs2(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'TEXT', nullable: false));
        self::assertSame([2, true], $spy->methodCalls['paragraphs'][0]);
    }

    #[Test]
    public function spyMediumTextCallsParagraphs3(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'MEDIUMTEXT', nullable: false));
        self::assertSame([3, true], $spy->methodCalls['paragraphs'][0]);
    }

    #[Test]
    public function spyLongTextCallsParagraphs5(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'LONGTEXT', nullable: false));
        self::assertSame([5, true], $spy->methodCalls['paragraphs'][0]);
    }

    #[Test]
    public function spyClobCallsParagraphs5(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'CLOB', nullable: false));
        self::assertSame([5, true], $spy->methodCalls['paragraphs'][0]);
    }

    #[Test]
    public function spyCharLexifyPattern(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'CHAR', length: 5, nullable: false));
        self::assertSame(['?????'], $spy->methodCalls['lexify'][0]);
    }

    #[Test]
    public function spyVarcharLexifyPattern(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'VARCHAR', length: 3, nullable: false));
        self::assertSame(['???'], $spy->methodCalls['lexify'][0]);
    }

    #[Test]
    public function spyTextWithLengthBoundary(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'TEXT', length: 100, nullable: false));
        self::assertSame([100], $spy->methodCalls['text'][0]);
    }

    #[Test]
    public function spyTextWithLengthCapAt200(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'TEXT', length: 500, nullable: false));
        self::assertSame([200], $spy->methodCalls['text'][0]);
    }

    #[Test]
    public function spyNumericDefaultBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'ANYTYPE', nullable: false));
        self::assertSame([2, -1000.0, 1000.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function spyNullableCallsBooleanWithTen(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new SqliteTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INTEGER', nullable: true));
        self::assertContains([10], $spy->booleanCalls);
    }

    #[Test]
    public function generateTinyTextLength(): void
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
    public function generateTextWithLengthLimit(): void
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
    public function generateTextWithLengthStartsFromBeginning(): void
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
    public function generateCharExactLength(): void
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
    public function generateBlobExactLengthOutput(): void
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
    public function generateBlobWithoutLengthNonEmpty(): void
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
    public function generateBooleanReturnsOneOrZero(): void
    {
        $faker = Factory::create();
        $mapper = new SqliteTypeMapper();

        $results = array_map(function (int $i) use ($faker, $mapper): mixed {
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
    public function nullableColumnDefaultRatioIsLow(): void
    {
        $faker = Factory::create();
        $mapper = new SqliteTypeMapper();
        $column = new ColumnDefinition('col', 'INTEGER', nullable: true, default: 'MARKER');

        $total = 500;
        $defaultCount = count(array_filter(array_map(function (int $i) use ($faker, $mapper, $column): mixed {
            $faker->seed($i);

            return $mapper->generate($faker, $column);
        }, range(0, $total - 1)), fn (mixed $value): bool => $value === 'MARKER'));
        self::assertLessThan((int) ($total * 0.5), $defaultCount, 'Default should be returned rarely (10% chance), not often (90%)');
    }

    #[Test]
    public function generateCharSubstrStartsAtZero(): void
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
    public function generateTextWithLengthSubstrStartsAtZero(): void
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
    public function generateTinyTextStartsFromBeginning(): void
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
