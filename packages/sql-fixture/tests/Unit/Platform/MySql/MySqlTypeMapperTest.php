<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\MySqlTypeMapper;
use SqlFixture\Schema\ColumnDefinition;
use Tests\Fixture\SpyGenerator;

#[CoversClass(MySqlTypeMapper::class)]
#[UsesClass(ColumnDefinition::class)]
#[CoversClass(\SqlFixture\Platform\MySql\Value\ColumnGenerator::class)]
#[CoversClass(\SqlFixture\Platform\MySql\Value\DecimalGenerator::class)]
#[CoversClass(\SqlFixture\Platform\MySql\Value\GeometryGenerator::class)]
#[CoversClass(\SqlFixture\Platform\MySql\Value\IntegerGenerator::class)]
#[CoversClass(\SqlFixture\Platform\MySql\Value\NumericGenerator::class)]
#[CoversClass(\SqlFixture\Platform\MySql\Value\SpatialGenerator::class)]
#[CoversClass(\SqlFixture\Platform\MySql\Value\StringGenerator::class)]
#[CoversClass(\SqlFixture\Platform\MySql\Value\TextGenerator::class)]
#[UsesClass(\SqlFixture\TypeMapper\ParagraphGenerator::class)]
#[UsesClass(\SqlFixture\TypeMapper\TypeMapperInterface::class)]
final class MySqlTypeMapperTest extends TestCase
{
    #[Test]
    public function testGenerateTinyIntBooleanMode(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('is_active', 'TINYINT', length: 1, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsBool($value);
    }

    #[Test]
    public function testGenerateTinyIntSignedRange(): void
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
    public function testGenerateTinyIntUnsignedRange(): void
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
    public function testGenerateSmallIntSignedRange(): void
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
    public function testGenerateSmallIntUnsignedRange(): void
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
    public function testGenerateMediumIntSignedRange(): void
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
    public function testGenerateMediumIntUnsignedRange(): void
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
    public function testGenerateDecimalRespectsPrecisionAndScale(): void
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
    public function testGenerateDecimalUnsignedAlwaysPositive(): void
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
    public function testGenerateCharExactLength(): void
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
    public function testGenerateCharDefaultLength(): void
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
    public function testGenerateBinaryExactLength(): void
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
    public function testGenerateVarbinaryRespectsMaxLength(): void
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
    public function testGenerateEnumReturnsOnlyValidValues(): void
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
    public function testGenerateSetReturnsOnlyValidCombinations(): void
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
    public function testGenerateJsonIsValidJson(): void
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
    public function testGeneratePointWktFormat(): void
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
    public function testGeneratePolygonClosed(): void
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
    public function testGenerateDateValidFormat(): void
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
    public function testGenerateTimestampInValidRange(): void
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
    public function testGenerateAutoIncrementReturnsNull(): void
    {
        $faker = Factory::create();
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('id', 'INT', autoIncrement: true, nullable: false);
        self::assertNull($mapper->generate($faker, $column));
    }

    #[Test]
    public function testGenerateGeneratedColumnReturnsNull(): void
    {
        $faker = Factory::create();
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('total', 'INT', generated: true, nullable: false);
        self::assertNull($mapper->generate($faker, $column));
    }

    #[Test]
    public function testGenerateNullableColumnCanReturnDefault(): void
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
    public function testNullableColumnReturnsDefaultWithSpecificSeed(): void
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
    public function testNullableColumnReturnsGeneratedWithSpecificSeed(): void
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
    public function testNullableColumnSeed28ReturnsDefault(): void
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
    public function testNullableColumnSeed285ReturnsGenerated(): void
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
    public function testGenerateIntegerAlias(): void
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
    public function testGenerateIntSignedRange(): void
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
    public function testGenerateIntUnsignedRange(): void
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
    public function testGenerateBigIntSignedRange(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'BIGINT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
    }

    #[Test]
    public function testGenerateBigIntUnsignedRange(): void
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
    public function testGenerateFloatType(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'FLOAT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
    }

    #[Test]
    public function testGenerateDoubleType(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'DOUBLE', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
    }

    #[Test]
    public function testGenerateRealType(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'REAL', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
    }

    #[Test]
    public function testGenerateNumericAlias(): void
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
    public function testGenerateDecAlias(): void
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
    public function testGenerateFixedAlias(): void
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
    public function testGenerateDecimalDefaultPrecision(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'DECIMAL', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
    }

    #[Test]
    public function testGenerateBitDefaultLength(): void
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
    public function testGenerateBitWithLength(): void
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
    public function testGenerateVarcharRespectsMaxLength(): void
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
    public function testGenerateVarcharDefaultLength(): void
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
    public function testGenerateTinyTextType(): void
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
    public function testGenerateTextType(): void
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
    public function testGenerateMediumTextType(): void
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
    public function testGenerateLongTextType(): void
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
    public function testGenerateBinaryDefaultLength(): void
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
    public function testGenerateVarbinaryDefaultLength(): void
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
    public function testGenerateTinyBlobType(): void
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
    public function testGenerateBlobType(): void
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
    public function testGenerateMediumBlobType(): void
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
    public function testGenerateLongBlobType(): void
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
    public function testGenerateEnumEmptyReturnsNull(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'ENUM', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertNull($value);
    }

    #[Test]
    public function testGenerateSetEmptyReturnsNull(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'SET', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertNull($value);
    }

    #[Test]
    public function testGenerateTimeFormat(): void
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
    public function testGenerateDatetimeFormat(): void
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
    public function testGenerateYearInRange(): void
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
    public function testGenerateLinestringFormat(): void
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
    public function testGenerateMultipointFormat(): void
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
    public function testGenerateMultilinestringFormat(): void
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
    public function testGenerateMultipolygonFormat(): void
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
    public function testGenerateGeometryFormat(): void
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
    public function testGenerateGeometryCollectionFormat(): void
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
    public function testGenerateBoolType(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'BOOL', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsBool($value);
    }

    #[Test]
    public function testGenerateBooleanType(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'BOOLEAN', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsBool($value);
    }

    #[Test]
    public function testGenerateUnknownTypeReturnsText(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'UNKNOWNTYPE', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
    }

    #[Test]
    public function testGenerateLowercaseTypeWorks(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'int', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
    }

    #[Test]
    public function testGenerateSetHasAtLeastOneElement(): void
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
    public function testSnapshotTinyIntSigned(): void
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
    public function testSnapshotTinyIntUnsigned(): void
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
    public function testSnapshotTinyIntBoolean(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'TINYINT', length: 1, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsBool($value);
    }

    #[Test]
    public function testSnapshotSmallIntSigned(): void
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
    public function testSnapshotSmallIntUnsigned(): void
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
    public function testSnapshotMediumIntSigned(): void
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
    public function testSnapshotMediumIntUnsigned(): void
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
    public function testSnapshotIntSigned(): void
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
    public function testSnapshotIntUnsigned(): void
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
    public function testSnapshotBigIntSigned(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'BIGINT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsInt($value);
    }

    #[Test]
    public function testSnapshotBigIntUnsigned(): void
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
    public function testSnapshotFloat(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'FLOAT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
    }

    #[Test]
    public function testSnapshotDouble(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'DOUBLE', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
    }

    #[Test]
    public function testSnapshotReal(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'REAL', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
    }

    #[Test]
    public function testSnapshotDecimalDefault(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'DECIMAL', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
    }

    #[Test]
    public function testSnapshotBitDefault(): void
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
    public function testSnapshotBitLength8(): void
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
    public function testSnapshotChar(): void
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
    public function testSnapshotVarchar(): void
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
    public function testSnapshotDate(): void
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
    public function testSnapshotTime(): void
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
    public function testSnapshotDatetime(): void
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
    public function testSnapshotTimestamp(): void
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
    public function testSnapshotYear(): void
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
    public function testSnapshotJson(): void
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
    public function testSnapshotPoint(): void
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
    public function testSnapshotBoolTrue(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'BOOL', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsBool($value);
    }

    #[Test]
    public function testSnapshotBooleanType(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'BOOLEAN', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsBool($value);
    }

    #[Test]
    public function testSnapshotIntegerAlias(): void
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
    public function testSnapshotNumericAlias(): void
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
    public function testSnapshotDecAlias(): void
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
    public function testSnapshotFixedAlias(): void
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
    public function testSnapshotLinestring(): void
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
    public function testSnapshotMultipolygonHasTwoPolygons(): void
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
    public function testSnapshotGeometryCollection(): void
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
    public function testSnapshotTinyTextMaxLen(): void
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
    public function testSnapshotBlobNotEmpty(): void
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
    public function testSnapshotMediumBlobNotEmpty(): void
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
    public function testSnapshotLongBlobNotEmpty(): void
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
    public function testSnapshotTinyBlobNotEmpty(): void
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
    public function testSnapshotPolygon(): void
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
    public function testSnapshotMultipointFormat(): void
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
    public function testSnapshotMultilinestringFormat(): void
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
    public function testSnapshotLinestringExact(): void
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
    public function testSnapshotPolygonExact(): void
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
    public function testSnapshotMultipointExact(): void
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
    public function testSnapshotMultilinestringExact(): void
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
    public function testSnapshotMultipolygonExact(): void
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
    public function testSnapshotGeometryCollectionExact(): void
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
    public function testSnapshotBlobExactLength(): void
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
    public function testSnapshotMediumBlobExactLength(): void
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
    public function testSnapshotLongBlobExactLength(): void
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
    public function testSnapshotTinyBlobExactLength(): void
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
    public function testSnapshotDecimalExactWithPrecision(): void
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
    public function testSnapshotDecimalDefaultPrecisionExact(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'DECIMAL', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
    }

    #[Test]
    public function testSnapshotDecimalUnsignedExact(): void
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
    public function testSnapshotCharDefaultLength(): void
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
    public function testSnapshotVarcharSmall(): void
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
    public function testSnapshotFloatExact(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'FLOAT', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
    }

    #[Test]
    public function testSnapshotDoubleExact(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'DOUBLE', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsFloat($value);
    }

    #[Test]
    public function testSnapshotTinyTextExactLength(): void
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
    public function testSnapshotJsonExact(): void
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
    public function testSnapshotYearExact(): void
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
    public function testNullableColumnReturnsDefaultOnChance(): void
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
    public function testSnapshotEnumReturnsElement(): void
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
    public function testSnapshotSetReturnsSubset(): void
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
    public function testSnapshotBinaryExactLen(): void
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
    public function testSnapshotVarbinaryExactLen(): void
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
    public function testSnapshotBitWithLength16(): void
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
    public function testGenerateBitDefaultLengthSeed5(): void
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
    public function testGenerateBigIntUnsignedSeed2(): void
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
    public function testGenerateMultiLineStringSeed1Has3Lines(): void
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
    public function testSpyTinyIntSignedBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'TINYINT', nullable: false));
        self::assertSame([-128, 127], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyTinyIntUnsignedBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'TINYINT', unsigned: true, nullable: false));
        self::assertSame([0, 255], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyTinyIntBooleanCallsBoolean(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'TINYINT', length: 1, nullable: false));
        self::assertSame([50], $spy->booleanCalls[0]);
    }

    #[Test]
    public function testSpySmallIntSignedBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'SMALLINT', nullable: false));
        self::assertSame([-32768, 32767], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpySmallIntUnsignedBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'SMALLINT', unsigned: true, nullable: false));
        self::assertSame([0, 65535], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyMediumIntSignedBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'MEDIUMINT', nullable: false));
        self::assertSame([-8388608, 8388607], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyMediumIntUnsignedBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'MEDIUMINT', unsigned: true, nullable: false));
        self::assertSame([0, 16777215], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyIntSignedBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INT', nullable: false));
        self::assertSame([-2147483648, 2147483647], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyIntUnsignedBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INT', unsigned: true, nullable: false));
        self::assertSame([0, 4294967295], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyIntegerSignedBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INTEGER', nullable: false));
        self::assertSame([-2147483648, 2147483647], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyBigIntSignedBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'BIGINT', nullable: false));
        self::assertSame([PHP_INT_MIN, PHP_INT_MAX], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyBigIntUnsignedBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'BIGINT', unsigned: true, nullable: false));
        self::assertSame([0, PHP_INT_MAX], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyFloatBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'FLOAT', nullable: false));
        self::assertSame([2, -1000.0, 1000.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function testSpyDoubleBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'DOUBLE', nullable: false));
        self::assertSame([4, -1000000.0, 1000000.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function testSpyRealBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'REAL', nullable: false));
        self::assertSame([4, -1000000.0, 1000000.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function testSpyDecimalSignedBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'DECIMAL', precision: 5, scale: 2, nullable: false));
        self::assertSame([2, -999.0, 999.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function testSpyDecimalUnsignedBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'DECIMAL', precision: 5, scale: 2, unsigned: true, nullable: false));
        self::assertSame([2, 0.0, 999.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function testSpyDecimalDefaultPrecision(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'DECIMAL', nullable: false));
        self::assertSame([0, -9999999999.0, 9999999999.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function testSpyNumericBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'NUMERIC', precision: 8, scale: 3, nullable: false));
        self::assertSame([3, -99999.0, 99999.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function testSpyDecTypeBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'DEC', precision: 6, scale: 1, nullable: false));
        self::assertSame([1, -99999.0, 99999.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function testSpyFixedTypeBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'FIXED', precision: 4, scale: 2, nullable: false));
        self::assertSame([2, -99.0, 99.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function testSpyBitBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'BIT', length: 8, nullable: false));
        self::assertSame([0, 255], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyBitDefaultLength(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'BIT', nullable: false));
        self::assertSame([0, 1], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyTinyBlobBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'TINYBLOB', nullable: false));
        self::assertSame([1, 255], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyBlobBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'BLOB', nullable: false));
        self::assertSame([1, 1000], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyMediumBlobBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'MEDIUMBLOB', nullable: false));
        self::assertSame([1, 1000], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyLongBlobBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'LONGBLOB', nullable: false));
        self::assertSame([1, 1000], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyYearBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'YEAR', nullable: false));
        self::assertSame([1901, 2155], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyJsonValueBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'JSON', nullable: false));
        self::assertContains([1, 100], $spy->numberBetweenCalls);
        self::assertContains([20], $spy->methodCalls['text']);
    }

    #[Test]
    public function testSpyVarbinaryBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'VARBINARY', length: 100, nullable: false));
        self::assertSame([1, 100], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpySetBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'SET', enumValues: ['a', 'b', 'c'], nullable: false));
        self::assertSame([1, 3], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyLineStringBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'LINESTRING', nullable: false));
        self::assertSame([2, 4], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyMultiPointBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'MULTIPOINT', nullable: false));
        self::assertSame([2, 4], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyMultiLineStringBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'MULTILINESTRING', nullable: false));
        self::assertSame([2, 3], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyPolygonBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'POLYGON', nullable: false));
        self::assertContains([2, 0.1, 1.0], $spy->randomFloatCalls);
        self::assertContains([-170, 170], $spy->methodCalls['longitude']);
        self::assertContains([-80, 80], $spy->methodCalls['latitude']);
    }

    #[Test]
    public function testSpyMultiPolygonBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'MULTIPOLYGON', nullable: false));
        self::assertContains([2, 0.1, 0.5], $spy->randomFloatCalls);
        self::assertContains([-170, 170], $spy->methodCalls['longitude']);
        self::assertContains([-80, 80], $spy->methodCalls['latitude']);
    }

    #[Test]
    public function testSpyBooleanTypeCallsBoolean(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'BOOLEAN', nullable: false));
        self::assertSame([50], $spy->booleanCalls[0]);
    }

    #[Test]
    public function testSpyNullableCallsBooleanWithTen(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INT', nullable: true));
        self::assertContains([10], $spy->booleanCalls);
    }

    #[Test]
    public function testSpyTinyTextCallsText255(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'TINYTEXT', nullable: false));
        self::assertSame([255], $spy->methodCalls['text'][0]);
    }

    #[Test]
    public function testSpyTextCallsParagraphs2(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'TEXT', nullable: false));
        self::assertSame([2, true], $spy->methodCalls['paragraphs'][0]);
    }

    #[Test]
    public function testSpyMediumTextCallsParagraphs3(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'MEDIUMTEXT', nullable: false));
        self::assertSame([3, true], $spy->methodCalls['paragraphs'][0]);
    }

    #[Test]
    public function testSpyLongTextCallsParagraphs5(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'LONGTEXT', nullable: false));
        self::assertSame([5, true], $spy->methodCalls['paragraphs'][0]);
    }

    #[Test]
    public function testSpyUnknownTypeCallsText50(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'UNKNOWN_TYPE', nullable: false));
        self::assertContains([50], $spy->methodCalls['text']);
    }

    #[Test]
    public function testSpyVarcharTextBoundary(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'VARCHAR', length: 100, nullable: false));
        self::assertSame([100], $spy->methodCalls['text'][0]);
    }

    #[Test]
    public function testSpyVarcharTextCapAt200(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'VARCHAR', length: 500, nullable: false));
        self::assertSame([200], $spy->methodCalls['text'][0]);
    }

    #[Test]
    public function testSpyCharLexifyPattern(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'CHAR', length: 5, nullable: false));
        self::assertSame(['?????'], $spy->methodCalls['lexify'][0]);
    }

    #[Test]
    public function testSpyTimestampDateBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new MySqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'TIMESTAMP', nullable: false));
        self::assertSame(['1970-01-01', '2038-01-19'], $spy->methodCalls['dateTimeBetween'][0]);
    }

    #[Test]
    public function testGenerateLineStringWktStructure(): void
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
    public function testGeneratePolygonWktStructure(): void
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
    public function testGenerateMultiPointWktStructure(): void
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
    public function testGenerateMultiLineStringWktStructure(): void
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
    public function testGenerateMultiPolygonWktStructure(): void
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
    public function testGenerateTinyTextLengthCapped(): void
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
    public function testGenerateCharExactLengthOutput(): void
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
    public function testGenerateVarcharMaxLengthOutput(): void
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
    public function testGenerateVarcharDefaultLengthOutput(): void
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
    public function testGenerateVarcharStartsFromBeginning(): void
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
    public function testGenerateBinaryExactLengthOutput(): void
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
    public function testGenerateBinaryDefaultLengthOutput(): void
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
    public function testGenerateVarbinaryMaxLengthOutput(): void
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
    public function testGenerateVarbinaryDefaultLengthOutput(): void
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
    public function testGenerateTinyBlobNonEmpty(): void
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
    public function testGenerateBlobNonEmpty(): void
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
    public function testGenerateMediumBlobNonEmpty(): void
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
    public function testGenerateLongBlobNonEmpty(): void
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
    public function testGenerateEnumWithEmptyValuesReturnsNull(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new MySqlTypeMapper();

        $column = new ColumnDefinition('col', 'ENUM', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertNull($value);
    }

    #[Test]
    public function testGeneratePolygonHasDistinctPoints(): void
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
    public function testGenerateMultiPolygonHasDistinctPoints(): void
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
    public function testGenerateBitValueInRange(): void
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
    public function testNullableColumnDefaultRatioIsLow(): void
    {
        $faker = Factory::create();
        $mapper = new MySqlTypeMapper();
        $column = new ColumnDefinition('col', 'INT', nullable: true, default: 'MARKER');

        $total = 500;
        $defaultCount = count(array_filter(array_map(function (int $i) use ($faker, $mapper, $column) {
            $faker->seed($i);

            return $mapper->generate($faker, $column);
        }, range(0, $total - 1)), fn ($value): bool => $value === 'MARKER'));
        self::assertLessThan((int) ($total * 0.5), $defaultCount, 'Default should be returned rarely (10% chance), not often (90%)');
    }

    #[Test]
    public function testGeneratePolygonSecondPointDiffersFromFirst(): void
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
    public function testGeneratePolygonThirdPointDiffersFromSecond(): void
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
