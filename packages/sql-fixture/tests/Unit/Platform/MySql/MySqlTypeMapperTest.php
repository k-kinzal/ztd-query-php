<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql;

use Faker\Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\MySqlTypeMapper;
use SqlFixture\Schema\ColumnDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Fixture\SpyGenerator;

/**
 * Tests for the platform MySqlTypeMapper (non-deprecated).
 * Focuses on edge cases and boundary value validation.
 */
#[CoversClass(MySqlTypeMapper::class)]
#[UsesClass(ColumnDefinition::class)]
final class MySqlTypeMapperTest extends TestCase
{
    #[Test]
    public function generateTinyIntBooleanMode(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('is_active', 'TINYINT', length: 1, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsBool($value);
    }

    #[Test]
    public function generateTinyIntSignedRange(): void
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
    public function generateTinyIntUnsignedRange(): void
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
    public function generateSmallIntSignedRange(): void
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
    public function generateSmallIntUnsignedRange(): void
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
    public function generateMediumIntSignedRange(): void
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
    public function generateMediumIntUnsignedRange(): void
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
    public function generateDecimalRespectsPrecisionAndScale(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'DECIMAL', precision: 6, scale: 3, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-999.999, $value);
        self::assertLessThanOrEqual(999.999, $value);
    }

    #[Test]
    public function generateDecimalUnsignedAlwaysPositive(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'DECIMAL', precision: 10, scale: 2, unsigned: true, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(0, $value);
        self::assertLessThanOrEqual(99999999.99, $value);
    }

    #[Test]
    public function generateCharExactLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'CHAR', length: 8, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertSame(8, strlen($value));
    }

    #[Test]
    public function generateCharDefaultLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'CHAR', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertSame(1, strlen($value));
    }

    #[Test]
    public function generateBinaryExactLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'BINARY', length: 32, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertSame(32, strlen($value));
    }

    #[Test]
    public function generateVarbinaryRespectsMaxLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'VARBINARY', length: 50, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertLessThanOrEqual(50, strlen($value));
        self::assertGreaterThan(0, strlen($value));
    }

    #[Test]
    public function generateEnumReturnsOnlyValidValues(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'ENUM', enumValues: ['red', 'green', 'blue'], nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertContains($value, ['red', 'green', 'blue']);
    }

    #[Test]
    public function generateSetReturnsOnlyValidCombinations(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'SET', enumValues: ['a', 'b', 'c'], nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        $parts = explode(',', $value);
        array_walk($parts, static function (string $part): void {
            self::assertContains($part, ['a', 'b', 'c']);
        });
    }

    #[Test]
    public function generateJsonIsValidJson(): void
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
    public function generatePointWktFormat(): void
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
    public function generatePolygonClosed(): void
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
    public function generateDateValidFormat(): void
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
    public function generateTimestampInValidRange(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'TIMESTAMP', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value);
        $timestamp = strtotime($value);
        self::assertGreaterThanOrEqual(strtotime('1970-01-01'), $timestamp);
        self::assertLessThanOrEqual(strtotime('2038-01-19'), $timestamp);
    }

    #[Test]
    public function generateAutoIncrementReturnsNull(): void
    {
        $faker = Factory::create();
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('id', 'INT', autoIncrement: true, nullable: false);
        self::assertNull($mapper->generate($faker, $column));
    }

    #[Test]
    public function generateGeneratedColumnReturnsNull(): void
    {
        $faker = Factory::create();
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('total', 'INT', generated: true, nullable: false);
        self::assertNull($mapper->generate($faker, $column));
    }

    #[Test]
    public function generateNullableColumnCanReturnDefault(): void
    {
        $faker = Factory::create();
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'INT', nullable: true, default: 'DEFAULT_VAL');

        [$sawDefault, $sawNonDefault] = (static function () use ($faker, $mapper, $column): array {
            $sawDefault = false;
            $sawNonDefault = false;
            for ($i = 0; $i < 200; $i++) {
                $faker->seed($i);
                $value = $mapper->generate($faker, $column);
                if ($value === 'DEFAULT_VAL') {
                    $sawDefault = true;
                } else {
                    $sawNonDefault = true;
                }
                if ($sawDefault && $sawNonDefault) {
                    break;
                }
            }

            return [$sawDefault, $sawNonDefault];
        })();
        self::assertTrue($sawDefault, 'Should sometimes return default');
        self::assertTrue($sawNonDefault, 'Should sometimes return generated value');
    }

    #[Test]
    public function nullableColumnReturnsDefaultWithSpecificSeed(): void
    {
        $faker = Factory::create();
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'INT', nullable: true, default: 'MARKER');

        [$sawDefault, $sawGenerated] = (static function () use ($faker, $mapper, $column): array {
            $sawDefault = false;
            $sawGenerated = false;
            for ($i = 0; $i < 200; $i++) {
                $faker->seed($i);
                $value = $mapper->generate($faker, $column);
                if ($value === 'MARKER') {
                    $sawDefault = true;
                } elseif (is_int($value)) {
                    $sawGenerated = true;
                }
                if ($sawDefault && $sawGenerated) {
                    break;
                }
            }

            return [$sawDefault, $sawGenerated];
        })();
        self::assertTrue($sawDefault, 'Nullable column should sometimes return default');
        self::assertTrue($sawGenerated, 'Nullable column should sometimes return generated value');
    }

    #[Test]
    public function nullableColumnReturnsGeneratedWithSpecificSeed(): void
    {
        $faker = Factory::create();
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'INT', nullable: true, default: 'MARKER');

        [$sawDefault, $sawGenerated] = (static function () use ($faker, $mapper, $column): array {
            $sawDefault = false;
            $sawGenerated = false;
            for ($i = 0; $i < 200; $i++) {
                $faker->seed($i);
                $value = $mapper->generate($faker, $column);
                if ($value === 'MARKER') {
                    $sawDefault = true;
                } elseif (is_int($value)) {
                    $sawGenerated = true;
                }
                if ($sawDefault && $sawGenerated) {
                    break;
                }
            }

            return [$sawDefault, $sawGenerated];
        })();
        self::assertTrue($sawDefault, 'Nullable column should sometimes return default');
        self::assertTrue($sawGenerated, 'Nullable column should sometimes return generated value');
    }

    #[Test]
    public function nullableColumnSeed28ReturnsDefault(): void
    {
        $faker = Factory::create();
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'INT', nullable: true, default: 'MARKER');

        [$sawDefault, $sawGenerated] = (static function () use ($faker, $mapper, $column): array {
            $sawDefault = false;
            $sawGenerated = false;
            for ($i = 0; $i < 200; $i++) {
                $faker->seed($i);
                $value = $mapper->generate($faker, $column);
                if ($value === 'MARKER') {
                    $sawDefault = true;
                } elseif (is_int($value)) {
                    $sawGenerated = true;
                }
                if ($sawDefault && $sawGenerated) {
                    break;
                }
            }

            return [$sawDefault, $sawGenerated];
        })();
        self::assertTrue($sawDefault, 'Nullable column should sometimes return default');
        self::assertTrue($sawGenerated, 'Nullable column should sometimes return generated value');
    }

    #[Test]
    public function nullableColumnSeed285ReturnsGenerated(): void
    {
        $faker = Factory::create();
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'INT', nullable: true, default: 'MARKER');

        [$sawDefault, $sawGenerated] = (static function () use ($faker, $mapper, $column): array {
            $sawDefault = false;
            $sawGenerated = false;
            for ($i = 0; $i < 200; $i++) {
                $faker->seed($i);
                $value = $mapper->generate($faker, $column);
                if ($value === 'MARKER') {
                    $sawDefault = true;
                } elseif (is_int($value)) {
                    $sawGenerated = true;
                }
                if ($sawDefault && $sawGenerated) {
                    break;
                }
            }

            return [$sawDefault, $sawGenerated];
        })();
        self::assertTrue($sawDefault, 'Nullable column should sometimes return default');
        self::assertTrue($sawGenerated, 'Nullable column should sometimes return generated value');
    }

    #[Test]
    public function generateIntegerAlias(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'INTEGER', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(-2147483648, $value);
        self::assertLessThanOrEqual(2147483647, $value);
    }

    #[Test]
    public function generateIntSignedRange(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'INT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(-2147483648, $value);
        self::assertLessThanOrEqual(2147483647, $value);
    }

    #[Test]
    public function generateIntUnsignedRange(): void
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
    public function generateBigIntSignedRange(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'BIGINT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
    }

    #[Test]
    public function generateBigIntUnsignedRange(): void
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
    public function generateFloatType(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'FLOAT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
    }

    #[Test]
    public function generateDoubleType(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'DOUBLE', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
    }

    #[Test]
    public function generateRealType(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'REAL', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
    }

    #[Test]
    public function generateNumericAlias(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'NUMERIC', precision: 5, scale: 2, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-999.99, $value);
        self::assertLessThanOrEqual(999.99, $value);
    }

    #[Test]
    public function generateDecAlias(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'DEC', precision: 5, scale: 2, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-999.99, $value);
        self::assertLessThanOrEqual(999.99, $value);
    }

    #[Test]
    public function generateFixedAlias(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'FIXED', precision: 5, scale: 2, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-999.99, $value);
        self::assertLessThanOrEqual(999.99, $value);
    }

    #[Test]
    public function generateDecimalDefaultPrecision(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'DECIMAL', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
    }

    #[Test]
    public function generateBitDefaultLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'BIT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(0, $value);
        self::assertLessThanOrEqual(1, $value);
    }

    #[Test]
    public function generateBitWithLength(): void
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
    public function generateVarcharRespectsMaxLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'VARCHAR', length: 50, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertLessThanOrEqual(50, strlen($value));
        self::assertGreaterThan(0, strlen($value));
    }

    #[Test]
    public function generateVarcharDefaultLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'VARCHAR', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertLessThanOrEqual(255, strlen($value));
        self::assertGreaterThan(0, strlen($value));
    }

    #[Test]
    public function generateTinyTextType(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'TINYTEXT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertLessThanOrEqual(255, strlen($value));
        self::assertGreaterThan(0, strlen($value));
    }

    #[Test]
    public function generateTextType(): void
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
    public function generateMediumTextType(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'MEDIUMTEXT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertGreaterThan(0, strlen($value));
    }

    #[Test]
    public function generateLongTextType(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'LONGTEXT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertGreaterThan(0, strlen($value));
    }

    #[Test]
    public function generateBinaryDefaultLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'BINARY', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertSame(1, strlen($value));
    }

    #[Test]
    public function generateVarbinaryDefaultLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'VARBINARY', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertGreaterThan(0, strlen($value));
    }

    #[Test]
    public function generateTinyBlobType(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'TINYBLOB', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertGreaterThan(0, strlen($value));
    }

    #[Test]
    public function generateBlobType(): void
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
    public function generateMediumBlobType(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'MEDIUMBLOB', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertGreaterThan(0, strlen($value));
    }

    #[Test]
    public function generateLongBlobType(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'LONGBLOB', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertGreaterThan(0, strlen($value));
    }

    #[Test]
    public function generateEnumEmptyReturnsNull(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'ENUM', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertNull($value);
    }

    #[Test]
    public function generateSetEmptyReturnsNull(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'SET', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertNull($value);
    }

    #[Test]
    public function generateTimeFormat(): void
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
    public function generateDatetimeFormat(): void
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
    public function generateYearInRange(): void
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
    public function generateLinestringFormat(): void
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
    public function generateMultipointFormat(): void
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
    public function generateMultilinestringFormat(): void
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
    public function generateMultipolygonFormat(): void
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
    public function generateGeometryFormat(): void
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
    public function generateGeometryCollectionFormat(): void
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
    public function generateBoolType(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'BOOL', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsBool($value);
    }

    #[Test]
    public function generateBooleanType(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'BOOLEAN', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsBool($value);
    }

    #[Test]
    public function generateUnknownTypeReturnsText(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'UNKNOWNTYPE', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
    }

    #[Test]
    public function generateLowercaseTypeWorks(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'int', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
    }

    #[Test]
    public function generateSetHasAtLeastOneElement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'SET', enumValues: ['x', 'y', 'z'], nullable: false);
        (static function () use ($faker, $mapper, $column): void {
            for ($i = 0; $i < 20; $i++) {
                $value = $mapper->generate($faker, $column);
                self::assertIsString($value);
                $parts = explode(',', $value);
                self::assertGreaterThanOrEqual(1, count($parts));
                self::assertLessThanOrEqual(3, count($parts));
            }
        })();
    }

    #[Test]
    public function snapshotTinyIntSigned(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'TINYINT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(-128, $value);
        self::assertLessThanOrEqual(127, $value);
    }

    #[Test]
    public function snapshotTinyIntUnsigned(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'TINYINT', unsigned: true, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(0, $value);
        self::assertLessThanOrEqual(255, $value);
    }

    #[Test]
    public function snapshotTinyIntBoolean(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'TINYINT', length: 1, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsBool($value);
    }

    #[Test]
    public function snapshotSmallIntSigned(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'SMALLINT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(-32768, $value);
        self::assertLessThanOrEqual(32767, $value);
    }

    #[Test]
    public function snapshotSmallIntUnsigned(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'SMALLINT', unsigned: true, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(0, $value);
        self::assertLessThanOrEqual(65535, $value);
    }

    #[Test]
    public function snapshotMediumIntSigned(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'MEDIUMINT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(-8388608, $value);
        self::assertLessThanOrEqual(8388607, $value);
    }

    #[Test]
    public function snapshotMediumIntUnsigned(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'MEDIUMINT', unsigned: true, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(0, $value);
        self::assertLessThanOrEqual(16777215, $value);
    }

    #[Test]
    public function snapshotIntSigned(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'INT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(-2147483648, $value);
        self::assertLessThanOrEqual(2147483647, $value);
    }

    #[Test]
    public function snapshotIntUnsigned(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'INT', unsigned: true, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(0, $value);
    }

    #[Test]
    public function snapshotBigIntSigned(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'BIGINT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
    }

    #[Test]
    public function snapshotBigIntUnsigned(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'BIGINT', unsigned: true, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(0, $value);
    }

    #[Test]
    public function snapshotFloat(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'FLOAT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
    }

    #[Test]
    public function snapshotDouble(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'DOUBLE', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
    }

    #[Test]
    public function snapshotReal(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'REAL', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
    }

    #[Test]
    public function snapshotDecimalDefault(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'DECIMAL', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
    }

    #[Test]
    public function snapshotBitDefault(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'BIT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(0, $value);
        self::assertLessThanOrEqual(1, $value);
    }

    #[Test]
    public function snapshotBitLength8(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'BIT', length: 8, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(0, $value);
        self::assertLessThanOrEqual(255, $value);
    }

    #[Test]
    public function snapshotChar(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'CHAR', length: 5, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertSame(5, strlen($value));
    }

    #[Test]
    public function snapshotVarchar(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'VARCHAR', length: 50, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertLessThanOrEqual(50, strlen($value));
        self::assertGreaterThan(0, strlen($value));
    }

    #[Test]
    public function snapshotDate(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
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
        $mapper = new MySqlTypeMapper();
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
        $mapper = new MySqlTypeMapper();
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
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'TIMESTAMP', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value);
        $timestamp = strtotime($value);
        self::assertGreaterThanOrEqual(strtotime('1970-01-01'), $timestamp);
        self::assertLessThanOrEqual(strtotime('2038-01-19'), $timestamp);
    }

    #[Test]
    public function snapshotYear(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'YEAR', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(1901, $value);
        self::assertLessThanOrEqual(2155, $value);
    }

    #[Test]
    public function snapshotJson(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
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
    public function snapshotPoint(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'POINT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertStringStartsWith('POINT(', $value);
    }

    #[Test]
    public function snapshotBoolTrue(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'BOOL', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsBool($value);
    }

    #[Test]
    public function snapshotBooleanType(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'BOOLEAN', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsBool($value);
    }

    #[Test]
    public function snapshotIntegerAlias(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'INTEGER', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(-2147483648, $value);
        self::assertLessThanOrEqual(2147483647, $value);
    }

    #[Test]
    public function snapshotNumericAlias(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'NUMERIC', precision: 5, scale: 2, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-999.99, $value);
        self::assertLessThanOrEqual(999.99, $value);
    }

    #[Test]
    public function snapshotDecAlias(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'DEC', precision: 5, scale: 2, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-999.99, $value);
        self::assertLessThanOrEqual(999.99, $value);
    }

    #[Test]
    public function snapshotFixedAlias(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'FIXED', precision: 5, scale: 2, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-999.99, $value);
        self::assertLessThanOrEqual(999.99, $value);
    }

    #[Test]
    public function snapshotLinestring(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'LINESTRING', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertStringStartsWith('LINESTRING(', $value);
    }

    #[Test]
    public function snapshotMultipolygonHasTwoPolygons(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'MULTIPOLYGON', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertStringStartsWith('MULTIPOLYGON(', $value);
    }

    #[Test]
    public function snapshotGeometryCollection(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'GEOMETRYCOLLECTION', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertStringStartsWith('GEOMETRYCOLLECTION(', $value);
    }

    #[Test]
    public function snapshotTinyTextMaxLen(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'TINYTEXT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertLessThanOrEqual(255, strlen($value));
        self::assertGreaterThan(0, strlen($value));
    }

    #[Test]
    public function snapshotBlobNotEmpty(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'BLOB', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertGreaterThan(0, strlen($value));
    }

    #[Test]
    public function snapshotMediumBlobNotEmpty(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'MEDIUMBLOB', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertGreaterThan(0, strlen($value));
    }

    #[Test]
    public function snapshotLongBlobNotEmpty(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'LONGBLOB', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertGreaterThan(0, strlen($value));
    }

    #[Test]
    public function snapshotTinyBlobNotEmpty(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'TINYBLOB', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertGreaterThan(0, strlen($value));
    }

    #[Test]
    public function snapshotPolygon(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'POLYGON', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertStringStartsWith('POLYGON((', $value);
    }

    #[Test]
    public function snapshotMultipointFormat(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'MULTIPOINT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertStringStartsWith('MULTIPOINT(', $value);
    }

    #[Test]
    public function snapshotMultilinestringFormat(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'MULTILINESTRING', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertStringStartsWith('MULTILINESTRING(', $value);
    }

    #[Test]
    public function snapshotLinestringExact(): void
    {
        $faker = Factory::create();
        $faker->seed(100);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'LINESTRING', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertStringStartsWith('LINESTRING(', $value);
    }

    #[Test]
    public function snapshotPolygonExact(): void
    {
        $faker = Factory::create();
        $faker->seed(100);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'POLYGON', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertStringStartsWith('POLYGON((', $value);
    }

    #[Test]
    public function snapshotMultipointExact(): void
    {
        $faker = Factory::create();
        $faker->seed(100);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'MULTIPOINT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertStringStartsWith('MULTIPOINT(', $value);
    }

    #[Test]
    public function snapshotMultilinestringExact(): void
    {
        $faker = Factory::create();
        $faker->seed(100);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'MULTILINESTRING', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertStringStartsWith('MULTILINESTRING(', $value);
    }

    #[Test]
    public function snapshotMultipolygonExact(): void
    {
        $faker = Factory::create();
        $faker->seed(100);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'MULTIPOLYGON', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertStringStartsWith('MULTIPOLYGON(', $value);
    }

    #[Test]
    public function snapshotGeometryCollectionExact(): void
    {
        $faker = Factory::create();
        $faker->seed(100);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'GEOMETRYCOLLECTION', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertStringStartsWith('GEOMETRYCOLLECTION(', $value);
    }

    #[Test]
    public function snapshotBlobExactLength(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'BLOB', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertGreaterThan(0, strlen($value));
    }

    #[Test]
    public function snapshotMediumBlobExactLength(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'MEDIUMBLOB', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertGreaterThan(0, strlen($value));
    }

    #[Test]
    public function snapshotLongBlobExactLength(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'LONGBLOB', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertGreaterThan(0, strlen($value));
    }

    #[Test]
    public function snapshotTinyBlobExactLength(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'TINYBLOB', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertGreaterThan(0, strlen($value));
    }

    #[Test]
    public function snapshotDecimalExactWithPrecision(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'DECIMAL', precision: 5, scale: 2, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-999.99, $value);
        self::assertLessThanOrEqual(999.99, $value);
    }

    #[Test]
    public function snapshotDecimalDefaultPrecisionExact(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'DECIMAL', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
    }

    #[Test]
    public function snapshotDecimalUnsignedExact(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'DECIMAL', precision: 10, scale: 2, unsigned: true, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(0, $value);
        self::assertLessThanOrEqual(99999999.99, $value);
    }

    #[Test]
    public function snapshotCharDefaultLength(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'CHAR', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertSame(1, strlen($value));
    }

    #[Test]
    public function snapshotVarcharSmall(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'VARCHAR', length: 10, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertLessThanOrEqual(10, strlen($value));
        self::assertGreaterThan(0, strlen($value));
    }

    #[Test]
    public function snapshotFloatExact(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'FLOAT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
    }

    #[Test]
    public function snapshotDoubleExact(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'DOUBLE', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
    }

    #[Test]
    public function snapshotTinyTextExactLength(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'TINYTEXT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertLessThanOrEqual(255, strlen($value));
        self::assertGreaterThan(0, strlen($value));
    }

    #[Test]
    public function snapshotJsonExact(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
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
    public function snapshotYearExact(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'YEAR', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(1901, $value);
        self::assertLessThanOrEqual(2155, $value);
    }

    #[Test]
    public function nullableColumnReturnsDefaultOnChance(): void
    {
        $faker = Factory::create();
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'INT', nullable: true, default: 99);

        [$gotDefault, $gotGenerated] = (static function () use ($faker, $mapper, $column): array {
            $gotDefault = false;
            $gotGenerated = false;
            for ($i = 0; $i < 200; $i++) {
                $faker->seed($i);
                $value = $mapper->generate($faker, $column);
                if ($value === 99) {
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
    public function snapshotEnumReturnsElement(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'ENUM', enumValues: ['a', 'b', 'c'], nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertContains($value, ['a', 'b', 'c']);
    }

    #[Test]
    public function snapshotSetReturnsSubset(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
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
    public function snapshotBinaryExactLen(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'BINARY', length: 16, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertSame(16, strlen($value));
    }

    #[Test]
    public function snapshotVarbinaryExactLen(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'VARBINARY', length: 100, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertLessThanOrEqual(100, strlen($value));
        self::assertGreaterThan(0, strlen($value));
    }

    #[Test]
    public function snapshotBitWithLength16(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'BIT', length: 16, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(0, $value);
        self::assertLessThanOrEqual(65535, $value);
    }

    #[Test]
    public function generateBitDefaultLengthSeed5(): void
    {
        $faker = Factory::create();
        $faker->seed(5);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'BIT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(0, $value);
        self::assertLessThanOrEqual(1, $value);
    }

    #[Test]
    public function generateBigIntUnsignedSeed2(): void
    {
        $faker = Factory::create();
        $faker->seed(2);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'BIGINT', unsigned: true, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(0, $value);
    }

    #[Test]
    public function generateMultiLineStringSeed1Has3Lines(): void
    {
        $faker = Factory::create();
        $faker->seed(1);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'MULTILINESTRING', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertStringStartsWith('MULTILINESTRING(', $value);
    }

    #[Test]
    public function spyTinyIntSignedBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'TINYINT', nullable: false));
        self::assertSame([-128, 127], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyTinyIntUnsignedBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'TINYINT', unsigned: true, nullable: false));
        self::assertSame([0, 255], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyTinyIntBooleanCallsBoolean(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'TINYINT', length: 1, nullable: false));
        self::assertSame([50], $spy->booleanCalls[0]);
    }

    #[Test]
    public function spySmallIntSignedBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'SMALLINT', nullable: false));
        self::assertSame([-32768, 32767], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spySmallIntUnsignedBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'SMALLINT', unsigned: true, nullable: false));
        self::assertSame([0, 65535], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyMediumIntSignedBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'MEDIUMINT', nullable: false));
        self::assertSame([-8388608, 8388607], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyMediumIntUnsignedBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'MEDIUMINT', unsigned: true, nullable: false));
        self::assertSame([0, 16777215], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyIntSignedBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INT', nullable: false));
        self::assertSame([-2147483648, 2147483647], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyIntUnsignedBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INT', unsigned: true, nullable: false));
        self::assertSame([0, 4294967295], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyIntegerSignedBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INTEGER', nullable: false));
        self::assertSame([-2147483648, 2147483647], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyBigIntSignedBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'BIGINT', nullable: false));
        self::assertSame([PHP_INT_MIN, PHP_INT_MAX], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyBigIntUnsignedBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'BIGINT', unsigned: true, nullable: false));
        self::assertSame([0, PHP_INT_MAX], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyFloatBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'FLOAT', nullable: false));
        self::assertSame([2, -1000.0, 1000.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function spyDoubleBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'DOUBLE', nullable: false));
        self::assertSame([4, -1000000.0, 1000000.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function spyRealBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'REAL', nullable: false));
        self::assertSame([4, -1000000.0, 1000000.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function spyDecimalSignedBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'DECIMAL', precision: 5, scale: 2, nullable: false));
        self::assertSame([2, -999.0, 999.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function spyDecimalUnsignedBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'DECIMAL', precision: 5, scale: 2, unsigned: true, nullable: false));
        self::assertSame([2, 0.0, 999.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function spyDecimalDefaultPrecision(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'DECIMAL', nullable: false));
        self::assertSame([0, -9999999999.0, 9999999999.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function spyNumericBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'NUMERIC', precision: 8, scale: 3, nullable: false));
        self::assertSame([3, -99999.0, 99999.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function spyDecTypeBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'DEC', precision: 6, scale: 1, nullable: false));
        self::assertSame([1, -99999.0, 99999.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function spyFixedTypeBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'FIXED', precision: 4, scale: 2, nullable: false));
        self::assertSame([2, -99.0, 99.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function spyBitBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'BIT', length: 8, nullable: false));
        self::assertSame([0, 255], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyBitDefaultLength(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'BIT', nullable: false));
        self::assertSame([0, 1], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyTinyBlobBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'TINYBLOB', nullable: false));
        self::assertSame([1, 255], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyBlobBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'BLOB', nullable: false));
        self::assertSame([1, 1000], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyMediumBlobBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'MEDIUMBLOB', nullable: false));
        self::assertSame([1, 1000], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyLongBlobBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'LONGBLOB', nullable: false));
        self::assertSame([1, 1000], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyYearBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'YEAR', nullable: false));
        self::assertSame([1901, 2155], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyJsonValueBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'JSON', nullable: false));
        self::assertContains([1, 100], $spy->numberBetweenCalls);
        self::assertContains([20], $spy->methodCalls['text']);
    }

    #[Test]
    public function spyVarbinaryBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'VARBINARY', length: 100, nullable: false));
        self::assertSame([1, 100], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spySetBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'SET', enumValues: ['a', 'b', 'c'], nullable: false));
        self::assertSame([1, 3], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyLineStringBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'LINESTRING', nullable: false));
        self::assertSame([2, 4], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyMultiPointBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'MULTIPOINT', nullable: false));
        self::assertSame([2, 4], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyMultiLineStringBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'MULTILINESTRING', nullable: false));
        self::assertSame([2, 3], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyPolygonBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'POLYGON', nullable: false));
        self::assertContains([2, 0.1, 1.0], $spy->randomFloatCalls);
        self::assertContains([-170, 170], $spy->methodCalls['longitude']);
        self::assertContains([-80, 80], $spy->methodCalls['latitude']);
    }

    #[Test]
    public function spyMultiPolygonBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'MULTIPOLYGON', nullable: false));
        self::assertContains([2, 0.1, 0.5], $spy->randomFloatCalls);
        self::assertContains([-170, 170], $spy->methodCalls['longitude']);
        self::assertContains([-80, 80], $spy->methodCalls['latitude']);
    }

    #[Test]
    public function spyBooleanTypeCallsBoolean(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'BOOLEAN', nullable: false));
        self::assertSame([50], $spy->booleanCalls[0]);
    }

    #[Test]
    public function spyNullableCallsBooleanWithTen(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INT', nullable: true));
        self::assertContains([10], $spy->booleanCalls);
    }

    #[Test]
    public function spyTinyTextCallsText255(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'TINYTEXT', nullable: false));
        self::assertSame([255], $spy->methodCalls['text'][0]);
    }

    #[Test]
    public function spyTextCallsParagraphs2(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'TEXT', nullable: false));
        self::assertSame([2, true], $spy->methodCalls['paragraphs'][0]);
    }

    #[Test]
    public function spyMediumTextCallsParagraphs3(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'MEDIUMTEXT', nullable: false));
        self::assertSame([3, true], $spy->methodCalls['paragraphs'][0]);
    }

    #[Test]
    public function spyLongTextCallsParagraphs5(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'LONGTEXT', nullable: false));
        self::assertSame([5, true], $spy->methodCalls['paragraphs'][0]);
    }

    #[Test]
    public function spyUnknownTypeCallsText50(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'UNKNOWN_TYPE', nullable: false));
        self::assertContains([50], $spy->methodCalls['text']);
    }

    #[Test]
    public function spyVarcharTextBoundary(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'VARCHAR', length: 100, nullable: false));
        self::assertSame([100], $spy->methodCalls['text'][0]);
    }

    #[Test]
    public function spyVarcharTextCapAt200(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'VARCHAR', length: 500, nullable: false));
        self::assertSame([200], $spy->methodCalls['text'][0]);
    }

    #[Test]
    public function spyCharLexifyPattern(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'CHAR', length: 5, nullable: false));
        self::assertSame(['?????'], $spy->methodCalls['lexify'][0]);
    }

    #[Test]
    public function spyTimestampDateBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'TIMESTAMP', nullable: false));
        self::assertSame(['1970-01-01', '2038-01-19'], $spy->methodCalls['dateTimeBetween'][0]);
    }

    #[Test]
    public function generateLineStringWktStructure(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'LINESTRING', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^LINESTRING\(.+\)$/', $value);
        self::assertStringStartsWith('LINESTRING(', $value);
        self::assertStringEndsWith(')', $value);
        $inner = substr($value, strlen('LINESTRING('), -1);
        $points = explode(',', $inner);
        self::assertGreaterThanOrEqual(2, count($points));
        array_map(
            fn (string $point) => self::assertMatchesRegularExpression('/^-?\d+\.\d+ -?\d+\.\d+$/', trim($point)),
            $points
        );
    }

    #[Test]
    public function generatePolygonWktStructure(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'POLYGON', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertStringStartsWith('POLYGON((', $value);
        self::assertStringEndsWith('))', $value);
        $inner = substr($value, strlen('POLYGON(('), -2);
        $points = explode(',', $inner);
        self::assertGreaterThanOrEqual(5, count($points));
        array_map(
            fn (string $point) => self::assertMatchesRegularExpression('/^-?\d+\.\d+ -?\d+\.\d+$/', trim($point)),
            $points
        );
    }

    #[Test]
    public function generateMultiPointWktStructure(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'MULTIPOINT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertStringStartsWith('MULTIPOINT(', $value);
        self::assertStringEndsWith(')', $value);
        $inner = substr($value, strlen('MULTIPOINT('), -1);
        self::assertGreaterThanOrEqual(2, substr_count($inner, '('));
    }

    #[Test]
    public function generateMultiLineStringWktStructure(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'MULTILINESTRING', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertStringStartsWith('MULTILINESTRING(', $value);
        self::assertStringEndsWith(')', $value);
        $inner = substr($value, strlen('MULTILINESTRING('), -1);
        self::assertGreaterThanOrEqual(2, substr_count($inner, '('));
        $lines = [];
        preg_match_all('/\(([^)]+)\)/', $inner, $lines);
        self::assertGreaterThanOrEqual(2, count($lines[1]));
        array_map(
            fn (string $line) => self::assertGreaterThanOrEqual(2, count(explode(',', $line))),
            $lines[1]
        );
    }

    #[Test]
    public function generateMultiPolygonWktStructure(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'MULTIPOLYGON', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertStringStartsWith('MULTIPOLYGON(', $value);
        self::assertStringEndsWith(')', $value);
        $inner = substr($value, strlen('MULTIPOLYGON('), -1);
        self::assertGreaterThanOrEqual(2, substr_count($inner, '(('));
    }

    #[Test]
    public function generateTinyTextLengthCapped(): void
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
    public function generateCharExactLengthOutput(): void
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
    public function generateVarcharMaxLengthOutput(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'VARCHAR', length: 10, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertLessThanOrEqual(10, strlen($value));
    }

    #[Test]
    public function generateVarcharDefaultLengthOutput(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'VARCHAR', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertLessThanOrEqual(255, strlen($value));
    }

    #[Test]
    public function generateVarcharStartsFromBeginning(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'VARCHAR', length: 200, nullable: false);
        $value1 = $mapper->generate($faker, $column);

        $faker->seed(12345);
        $text = $faker->text(min(200, 200));
        $expected = substr($text, 0, 200);
        self::assertSame($expected, $value1);
    }

    #[Test]
    public function generateBinaryExactLengthOutput(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'BINARY', length: 8, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertSame(8, strlen($value));
    }

    #[Test]
    public function generateBinaryDefaultLengthOutput(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'BINARY', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertSame(1, strlen($value));
    }

    #[Test]
    public function generateVarbinaryMaxLengthOutput(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'VARBINARY', length: 10, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertGreaterThanOrEqual(1, strlen($value));
        self::assertLessThanOrEqual(10, strlen($value));
    }

    #[Test]
    public function generateVarbinaryDefaultLengthOutput(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'VARBINARY', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertGreaterThanOrEqual(1, strlen($value));
        self::assertLessThanOrEqual(255, strlen($value));
    }

    #[Test]
    public function generateTinyBlobNonEmpty(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'TINYBLOB', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertGreaterThanOrEqual(1, strlen($value));
    }

    #[Test]
    public function generateBlobNonEmpty(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'BLOB', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertGreaterThanOrEqual(1, strlen($value));
    }

    #[Test]
    public function generateMediumBlobNonEmpty(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'MEDIUMBLOB', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertGreaterThanOrEqual(1, strlen($value));
    }

    #[Test]
    public function generateLongBlobNonEmpty(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'LONGBLOB', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertGreaterThanOrEqual(1, strlen($value));
    }

    #[Test]
    public function generateEnumWithEmptyValuesReturnsNull(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'ENUM', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertNull($value);
    }

    #[Test]
    public function generatePolygonHasDistinctPoints(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'POLYGON', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        $inner = substr($value, strlen('POLYGON(('), -2);
        $points = explode(',', $inner);
        $firstPoint = trim($points[0]);
        $secondPoint = trim($points[1]);
        $thirdPoint = trim($points[2]);
        self::assertNotSame($firstPoint, $secondPoint);
        self::assertNotSame($secondPoint, $thirdPoint);
    }

    #[Test]
    public function generateMultiPolygonHasDistinctPoints(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'MULTIPOLYGON', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertSame(2, substr_count($value, '(('));
        $inner = substr($value, strlen('MULTIPOLYGON('), -1);
        $parts = explode(')),((', $inner);
        self::assertSame(2, count($parts));
        array_map(
            function (string $part): void {
                $cleaned = trim($part, '()');
                $points = explode(',', $cleaned);
                self::assertGreaterThanOrEqual(4, count($points));
                self::assertNotSame(trim($points[0]), trim($points[1]));
            },
            $parts
        );
    }

    #[Test]
    public function generateBitValueInRange(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'BIT', length: 4, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(0, $value);
        self::assertLessThanOrEqual(15, $value);
    }

    #[Test]
    public function nullableColumnDefaultRatioIsLow(): void
    {
        $faker = Factory::create();
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'INT', nullable: true, default: 'MARKER');

        $total = 500;
        $defaultCount = count(array_filter(array_map(function (int $i) use ($faker, $mapper, $column): mixed {
            $faker->seed($i);

            return $mapper->generate($faker, $column);
        }, range(0, $total - 1)), fn (mixed $value): bool => $value === 'MARKER'));
        self::assertLessThan((int) ($total * 0.5), $defaultCount, 'Default should be returned rarely (10% chance), not often (90%)');
    }

    #[Test]
    public function generatePolygonSecondPointDiffersFromFirst(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'POLYGON', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        $inner = substr($value, strlen('POLYGON(('), -2);
        $points = explode(',', $inner);
        self::assertGreaterThanOrEqual(5, count($points));
        $first = trim($points[0]);
        $second = trim($points[1]);
        self::assertNotSame($first, $second);
        $last = trim($points[count($points) - 1]);
        self::assertSame($first, $last);
    }

    #[Test]
    public function generatePolygonThirdPointDiffersFromSecond(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'POLYGON', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        $inner = substr($value, strlen('POLYGON(('), -2);
        $points = explode(',', $inner);
        $second = trim($points[1]);
        $third = trim($points[2]);
        self::assertNotSame($second, $third);
    }
}
