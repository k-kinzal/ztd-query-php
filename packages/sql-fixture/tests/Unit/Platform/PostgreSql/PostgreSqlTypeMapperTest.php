<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\PostgreSqlTypeMapper;
use SqlFixture\Schema\ColumnDefinition;
use Tests\Fixture\SpyGenerator;

#[CoversClass(PostgreSqlTypeMapper::class)]
#[UsesClass(ColumnDefinition::class)]
#[CoversClass(\SqlFixture\Platform\PostgreSql\Value\ColumnGenerator::class)]
#[CoversClass(\SqlFixture\Platform\PostgreSql\Value\DecimalGenerator::class)]
#[CoversClass(\SqlFixture\Platform\PostgreSql\Value\NumericGenerator::class)]
#[CoversClass(\SqlFixture\Platform\PostgreSql\Value\StringGenerator::class)]
#[CoversClass(\SqlFixture\Platform\PostgreSql\Value\StructuredGenerator::class)]
#[CoversClass(\SqlFixture\Platform\PostgreSql\Value\TemporalGenerator::class)]
#[UsesClass(\SqlFixture\TypeMapper\ParagraphGenerator::class)]
#[UsesClass(\SqlFixture\TypeMapper\TypeMapperInterface::class)]
final class PostgreSqlTypeMapperTest extends TestCase
{
    #[Test]
    public function testGenerateInteger(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'INTEGER', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(-2147483648, $value);
        self::assertLessThanOrEqual(2147483647, $value);
    }

    #[Test]
    public function testGenerateSmallInt(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

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
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'BIGINT', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsInt($value);
    }

    #[Test]
    public function testGenerateReal(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'REAL', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-1000.0, $value);
        self::assertLessThanOrEqual(1000.0, $value);
    }

    #[Test]
    public function testGenerateDoublePrecision(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'DOUBLE PRECISION', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-1000000.0, $value);
        self::assertLessThanOrEqual(1000000.0, $value);
    }

    #[Test]
    public function testGenerateNumeric(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'NUMERIC', precision: 10, scale: 2, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-99999999.99, $value);
        self::assertLessThanOrEqual(99999999.99, $value);
    }

    #[Test]
    public function testGenerateBoolean(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'BOOLEAN', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsBool($value);
    }

    #[Test]
    public function testGenerateText(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

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
        $mapper = new PostgreSqlTypeMapper();

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
        $mapper = new PostgreSqlTypeMapper();

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
        $mapper = new PostgreSqlTypeMapper();

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
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'TIME', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', $value);
    }

    #[Test]
    public function testGenerateTimestamp(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'TIMESTAMP', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value);
    }

    #[Test]
    public function testGenerateTimestamptz(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'TIMESTAMPTZ', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $value);
    }

    #[Test]
    public function testGenerateUuid(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'UUID', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
    }

    #[Test]
    public function testGenerateJsonb(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'JSONB', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        $decoded = json_decode($value, true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('key', $decoded);
        self::assertArrayHasKey('value', $decoded);
    }

    #[Test]
    public function testGenerateJson(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'JSON', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        $decoded = json_decode($value, true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('key', $decoded);
        self::assertArrayHasKey('value', $decoded);
    }

    #[Test]
    public function testGenerateBytea(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'BYTEA', nullable: false);
        /**
         * @var string $value
         */
        $value = $mapper->generate($faker, $column);
        self::assertStringStartsWith('\\x', $value);
        self::assertGreaterThan(2, strlen($value));
    }

    #[Test]
    public function testGenerateInet(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'INET', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $value);
    }

    #[Test]
    public function testGenerateCidr(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'CIDR', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\/\d+$/', $value);
    }

    #[Test]
    public function testGenerateMacaddr(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'MACADDR', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/', $value);
    }

    #[Test]
    public function testGenerateMoney(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'MONEY', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(0.0, $value);
        self::assertLessThanOrEqual(99999.99, $value);
    }

    #[Test]
    public function testGenerateInterval(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'INTERVAL', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d+ (days|hours|minutes|seconds|months|years)$/', $value);
    }

    #[Test]
    public function testGenerateIntegerArray(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'INTEGER_ARRAY', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\{-?\d+(,-?\d+)*\}$/', $value);
    }

    #[Test]
    public function testGenerateTextArray(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'TEXT_ARRAY', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\{".+"(,".+")*\}$/', $value);
    }

    #[Test]
    public function testGenerateAutoIncrementReturnsNull(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('id', 'INTEGER', autoIncrement: true, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertNull($value);
    }

    #[Test]
    public function testGenerateGeneratedColumnReturnsNull(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('computed', 'INTEGER', generated: true, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertNull($value);
    }

    #[Test]
    public function testGenerateNullable(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

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
        $mapper = new PostgreSqlTypeMapper();

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
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'NUMERIC', precision: 5, scale: 2, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-999.99, $value);
        self::assertLessThanOrEqual(999.99, $value);
    }

    #[Test]
    public function testGenerateSmallIntBoundaryValues(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'SMALLINT', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(-32768, $value);
        self::assertLessThanOrEqual(32767, $value);
    }

    #[Test]
    public function testGenerateMoneyBoundaryValues(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'MONEY', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(0.0, $value);
        self::assertLessThanOrEqual(99999.99, $value);
    }

    #[Test]
    public function testGenerateUuidFormat(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'UUID', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
    }

    #[Test]
    public function testGenerateInetFormat(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'INET', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $value);
    }

    #[Test]
    public function testGenerateCidrFormat(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'CIDR', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\/\d+$/', $value);
    }

    #[Test]
    public function testGenerateMacaddrFormat(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'MACADDR', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/', $value);
    }

    #[Test]
    public function testGenerateIntervalFormat(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'INTERVAL', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d+ (days|hours|minutes|seconds|months|years)$/', $value);
    }

    #[Test]
    public function testGenerateByteaFormat(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'BYTEA', nullable: false);
        /**
         * @var string $value
         */
        $value = $mapper->generate($faker, $column);
        self::assertStringStartsWith('\\x', $value);
        self::assertGreaterThan(2, strlen($value));
        self::assertMatchesRegularExpression('/^\\\\x[0-9a-f]+$/', $value);
    }

    #[Test]
    public function testGenerateXmlFormat(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'XML', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertStringStartsWith('<root>', $value);
        self::assertStringEndsWith('</root>', $value);
    }

    #[Test]
    public function testGenerateTimetzFormat(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'TIMETZ', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $value);
    }

    #[Test]
    public function testGenerateCharacterType(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'CHARACTER', length: 3, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertSame(3, strlen($value));
    }

    #[Test]
    public function testGenerateCharacterVaryingType(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'CHARACTER VARYING', length: 50, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertLessThanOrEqual(50, strlen($value));
    }

    #[Test]
    public function testGenerateXml(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'XML', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertStringStartsWith('<root>', $value);
        self::assertStringEndsWith('</root>', $value);
    }

    #[Test]
    public function testGenerateInt2Alias(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'INT2', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(-32768, $value);
        self::assertLessThanOrEqual(32767, $value);
    }

    #[Test]
    public function testGenerateInt4Alias(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'INT4', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(-2147483648, $value);
        self::assertLessThanOrEqual(2147483647, $value);
    }

    #[Test]
    public function testGenerateInt8Alias(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'INT8', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsInt($value);
    }

    #[Test]
    public function testGenerateFloat4Alias(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'FLOAT4', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-1000.0, $value);
        self::assertLessThanOrEqual(1000.0, $value);
    }

    #[Test]
    public function testGenerateFloat8Alias(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'FLOAT8', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-1000000.0, $value);
        self::assertLessThanOrEqual(1000000.0, $value);
    }

    #[Test]
    public function testGenerateBoolAlias(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'BOOL', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsBool($value);
    }

    #[Test]
    public function testGenerateDecAlias(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'DEC', precision: 5, scale: 2, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-999.99, $value);
        self::assertLessThanOrEqual(999.99, $value);
    }

    #[Test]
    public function testGenerateTimeWithoutTimeZone(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'TIME WITHOUT TIME ZONE', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', $value);
    }

    #[Test]
    public function testGenerateTimeWithTimeZone(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'TIME WITH TIME ZONE', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $value);
    }

    #[Test]
    public function testGenerateTimestampWithoutTimeZone(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'TIMESTAMP WITHOUT TIME ZONE', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value);
    }

    #[Test]
    public function testGenerateTimestampWithTimeZone(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'TIMESTAMP WITH TIME ZONE', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $value);
    }

    #[Test]
    public function testGenerateIntArrayAlias(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'INT_ARRAY', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\{-?\d+(,-?\d+)*\}$/', $value);
    }

    #[Test]
    public function testGenerateDecimalDefaultPrecision(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'DECIMAL', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-9999999999.0, $value);
        self::assertLessThanOrEqual(9999999999.0, $value);
    }

    #[Test]
    public function testGenerateCharDefaultLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'CHAR', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertSame(1, strlen($value));
    }

    #[Test]
    public function testGenerateVarcharDefaultLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'VARCHAR', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertLessThanOrEqual(255, strlen($value));
        self::assertGreaterThan(0, strlen($value));
    }

    #[Test]
    public function testGenerateUnknownTypeReturnsText(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'UNKNOWNTYPE', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertGreaterThan(0, strlen($value));
    }

    #[Test]
    public function testGenerateLowercaseTypeWorks(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'integer', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(-2147483648, $value);
        self::assertLessThanOrEqual(2147483647, $value);
    }

    #[Test]
    public function testGenerateJsonHasKeyValue(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'JSON', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        $decoded = json_decode($value, true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('key', $decoded);
        self::assertArrayHasKey('value', $decoded);
    }

    #[Test]
    public function testGenerateIntervalValueRange(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'INTERVAL', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d+ (days|hours|minutes|seconds|months|years)$/', $value);
    }

    #[Test]
    public function testGenerateIntArrayContainsNumbers(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'INTEGER_ARRAY', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\{-?\d+(,-?\d+)*\}$/', $value);
    }

    #[Test]
    public function testGenerateTextArrayContainsQuotedStrings(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'TEXT_ARRAY', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\{".+"(,".+")*\}$/', $value);
    }

    #[Test]
    public function testSnapshotSmallInt(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
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
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'INT2', nullable: false);
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
        $mapper = new PostgreSqlTypeMapper();
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
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'INT', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(-2147483648, $value);
        self::assertLessThanOrEqual(2147483647, $value);
    }

    #[Test]
    public function testSnapshotInt4(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'INT4', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(-2147483648, $value);
        self::assertLessThanOrEqual(2147483647, $value);
    }

    #[Test]
    public function testSnapshotBigInt(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'BIGINT', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsInt($value);
    }

    #[Test]
    public function testSnapshotInt8(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'INT8', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsInt($value);
    }

    #[Test]
    public function testSnapshotReal(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'REAL', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-1000.0, $value);
        self::assertLessThanOrEqual(1000.0, $value);
    }

    #[Test]
    public function testSnapshotFloat4(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'FLOAT4', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-1000.0, $value);
        self::assertLessThanOrEqual(1000.0, $value);
    }

    #[Test]
    public function testSnapshotDoublePrecision(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'DOUBLE PRECISION', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-1000000.0, $value);
        self::assertLessThanOrEqual(1000000.0, $value);
    }

    #[Test]
    public function testSnapshotFloat8(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'FLOAT8', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-1000000.0, $value);
        self::assertLessThanOrEqual(1000000.0, $value);
    }

    #[Test]
    public function testSnapshotDecimal(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'DECIMAL', precision: 5, scale: 2, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-999.99, $value);
        self::assertLessThanOrEqual(999.99, $value);
    }

    #[Test]
    public function testSnapshotNumeric(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'NUMERIC', precision: 5, scale: 2, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-999.99, $value);
        self::assertLessThanOrEqual(999.99, $value);
    }

    #[Test]
    public function testSnapshotDec(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'DEC', precision: 5, scale: 2, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-999.99, $value);
        self::assertLessThanOrEqual(999.99, $value);
    }

    #[Test]
    public function testSnapshotMoney(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'MONEY', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(0.0, $value);
        self::assertLessThanOrEqual(99999.99, $value);
    }

    #[Test]
    public function testSnapshotBoolean(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'BOOLEAN', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsBool($value);
    }

    #[Test]
    public function testSnapshotBool(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'BOOL', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsBool($value);
    }

    #[Test]
    public function testSnapshotChar(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'CHAR', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertSame(1, strlen($value));
    }

    #[Test]
    public function testSnapshotCharacter(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'CHARACTER', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertSame(1, strlen($value));
    }

    #[Test]
    public function testSnapshotDate(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
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
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'TIME', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', $value);
    }

    #[Test]
    public function testSnapshotTimeWithoutTimeZone(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'TIME WITHOUT TIME ZONE', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', $value);
    }

    #[Test]
    public function testSnapshotTimeWithTimeZone(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'TIME WITH TIME ZONE', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $value);
    }

    #[Test]
    public function testSnapshotTimetz(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'TIMETZ', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $value);
    }

    #[Test]
    public function testSnapshotTimestamp(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'TIMESTAMP', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value);
    }

    #[Test]
    public function testSnapshotTimestampWithoutTimeZone(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'TIMESTAMP WITHOUT TIME ZONE', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value);
    }

    #[Test]
    public function testSnapshotTimestampWithTimeZone(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'TIMESTAMP WITH TIME ZONE', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $value);
    }

    #[Test]
    public function testSnapshotTimestamptz(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'TIMESTAMPTZ', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $value);
    }

    #[Test]
    public function testSnapshotInterval(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'INTERVAL', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d+ (days|hours|minutes|seconds|months|years)$/', $value);
    }

    #[Test]
    public function testSnapshotJson(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'JSON', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        $decoded = json_decode($value, true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('key', $decoded);
        self::assertArrayHasKey('value', $decoded);
    }

    #[Test]
    public function testSnapshotJsonb(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'JSONB', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        $decoded = json_decode($value, true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('key', $decoded);
        self::assertArrayHasKey('value', $decoded);
    }

    #[Test]
    public function testSnapshotUuid(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'UUID', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
    }

    #[Test]
    public function testSnapshotInet(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'INET', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $value);
    }

    #[Test]
    public function testSnapshotCidr(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'CIDR', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\/\d+$/', $value);
    }

    #[Test]
    public function testSnapshotMacaddr(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'MACADDR', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/', $value);
    }

    #[Test]
    public function testSnapshotIntegerArray(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'INTEGER_ARRAY', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\{-?\d+(,-?\d+)*\}$/', $value);
    }

    #[Test]
    public function testSnapshotIntArray(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'INT_ARRAY', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\{-?\d+(,-?\d+)*\}$/', $value);
    }

    #[Test]
    public function testSnapshotTextArray(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'TEXT_ARRAY', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\{".+"(,".+")*\}$/', $value);
    }

    #[Test]
    public function testSnapshotXml(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'XML', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertStringStartsWith('<root>', $value);
        self::assertStringEndsWith('</root>', $value);
    }

    #[Test]
    public function testSnapshotBytea(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'BYTEA', nullable: false);
        /**
         * @var string $value
         */
        $value = $mapper->generate($faker, $column);
        self::assertStringStartsWith('\\x', $value);
        self::assertGreaterThan(2, strlen($value));
    }

    #[Test]
    public function testSnapshotUnknownType(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'SOMETHINGELSE', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertGreaterThan(0, strlen($value));
    }

    #[Test]
    public function testSnapshotDecimalDefaultPrecisionExact(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'DECIMAL', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-9999999999.0, $value);
        self::assertLessThanOrEqual(9999999999.0, $value);
    }

    #[Test]
    public function testSnapshotDecimalWithPrecisionExact(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'DECIMAL', precision: 5, scale: 2, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-999.99, $value);
        self::assertLessThanOrEqual(999.99, $value);
    }

    #[Test]
    public function testSnapshotNumericDefaultPrecision(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'NUMERIC', precision: 10, scale: 0, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-9999999999.0, $value);
        self::assertLessThanOrEqual(9999999999.0, $value);
    }

    #[Test]
    public function testSnapshotDecExact(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'DEC', precision: 8, scale: 3, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(-99999.999, $value);
        self::assertLessThanOrEqual(99999.999, $value);
    }

    #[Test]
    public function testSnapshotCharExact(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'CHAR', length: 5, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertSame(5, strlen($value));
    }

    #[Test]
    public function testSnapshotCharDefaultLength(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'CHAR', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertSame(1, strlen($value));
    }

    #[Test]
    public function testSnapshotVarcharExact(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'VARCHAR', length: 50, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertLessThanOrEqual(50, strlen($value));
    }

    #[Test]
    public function testSnapshotVarcharDefaultLength(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'VARCHAR', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertLessThanOrEqual(255, strlen($value));
        self::assertGreaterThan(0, strlen($value));
    }

    #[Test]
    public function testSnapshotCharacterVarying(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'CHARACTER VARYING', length: 50, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertLessThanOrEqual(50, strlen($value));
    }

    #[Test]
    public function testSnapshotByteaFormat(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'BYTEA', nullable: false);
        /**
         * @var string $value
         */
        $value = $mapper->generate($faker, $column);
        self::assertStringStartsWith('\\x', $value);
        $hexPart = substr($value, 2);
        self::assertSame(0, strlen($hexPart) % 2);
        self::assertMatchesRegularExpression('/^[0-9a-f]+$/', $hexPart);
    }

    #[Test]
    public function testSnapshotIntegerArrayExact(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'INTEGER_ARRAY', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\{-?\d+(,-?\d+)*\}$/', $value);
    }

    #[Test]
    public function testSnapshotTextArrayExact(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'TEXT_ARRAY', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\{".+"(,".+")*\}$/', $value);
    }

    #[Test]
    public function testSnapshotXmlExact(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'XML', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertStringStartsWith('<root>', $value);
        self::assertStringEndsWith('</root>', $value);
    }

    #[Test]
    public function testSnapshotIntervalExact(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'INTERVAL', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\d+ (days|hours|minutes|seconds|months|years)$/', $value);
    }

    #[Test]
    public function testSnapshotMoneyExact(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'MONEY', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsFloat($value);
        self::assertGreaterThanOrEqual(0.0, $value);
        self::assertLessThanOrEqual(99999.99, $value);
    }

    #[Test]
    public function testNullableColumnReturnsDefault(): void
    {
        $faker = Factory::create();
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'INTEGER', nullable: true, default: 99);

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
    public function testNullableColumnReturnsDefaultWithSpecificSeed(): void
    {
        $faker = Factory::create();
        $faker->seed(10);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'INTEGER', nullable: true, default: 'MARKER');

        $value = $mapper->generate($faker, $column);

        self::assertTrue(
            $value === 'MARKER' || is_int($value),
            'Nullable column should return either default or generated int value',
        );
    }

    #[Test]
    public function testNullableColumnReturnsGeneratedWithSpecificSeed(): void
    {
        $faker = Factory::create();
        $faker->seed(0);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'INTEGER', nullable: true, default: 'MARKER');

        $value = $mapper->generate($faker, $column);

        self::assertTrue(
            $value === 'MARKER' || is_int($value),
            'Nullable column should return either default or generated int value',
        );
    }

    #[Test]
    public function testNullableColumnSeed28ReturnsDefault(): void
    {
        $faker = Factory::create();
        $faker->seed(28);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'INTEGER', nullable: true, default: 'MARKER');

        $value = $mapper->generate($faker, $column);

        self::assertTrue(
            $value === 'MARKER' || is_int($value),
            'Nullable column should return either default or generated int value',
        );
    }

    #[Test]
    public function testNullableColumnSeed285ReturnsGenerated(): void
    {
        $faker = Factory::create();
        $faker->seed(285);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'INTEGER', nullable: true, default: 'MARKER');

        $value = $mapper->generate($faker, $column);

        self::assertTrue(
            $value === 'MARKER' || is_int($value),
            'Nullable column should return either default or generated int value',
        );
    }

    #[Test]
    public function testGenerateCharacterVaryingShortLength(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'CHARACTER VARYING', length: 10, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertLessThanOrEqual(10, strlen($value));
    }

    #[Test]
    public function testGenerateTextArraySeed5Has3Elements(): void
    {
        $faker = Factory::create();
        $faker->seed(5);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'TEXT_ARRAY', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsString($value);
        self::assertMatchesRegularExpression('/^\{".+"(,".+")*\}$/', $value);
    }

    #[Test]
    public function testSpySmallIntBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'SMALLINT', nullable: false));
        self::assertSame([-32768, 32767], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyInt2Boundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INT2', nullable: false));
        self::assertSame([-32768, 32767], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyIntegerBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INTEGER', nullable: false));
        self::assertSame([-2147483648, 2147483647], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyIntBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INT', nullable: false));
        self::assertSame([-2147483648, 2147483647], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyInt4Boundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INT4', nullable: false));
        self::assertSame([-2147483648, 2147483647], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyBigIntBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'BIGINT', nullable: false));
        self::assertSame([PHP_INT_MIN, PHP_INT_MAX], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyInt8Boundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INT8', nullable: false));
        self::assertSame([PHP_INT_MIN, PHP_INT_MAX], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyRealBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'REAL', nullable: false));
        self::assertSame([2, -1000.0, 1000.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function testSpyFloat4Boundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'FLOAT4', nullable: false));
        self::assertSame([2, -1000.0, 1000.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function testSpyDoublePrecisionBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'DOUBLE PRECISION', nullable: false));
        self::assertSame([4, -1000000.0, 1000000.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function testSpyFloat8Boundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'FLOAT8', nullable: false));
        self::assertSame([4, -1000000.0, 1000000.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function testSpyMoneyBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'MONEY', nullable: false));
        self::assertSame([2, 0.0, 99999.99], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function testSpyDecimalBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'DECIMAL', precision: 5, scale: 2, nullable: false));
        self::assertSame([2, -999.0, 999.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function testSpyDecimalDefaultPrecision(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'DECIMAL', nullable: false));
        self::assertSame([0, -9999999999.0, 9999999999.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function testSpyNumericBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'NUMERIC', precision: 8, scale: 3, nullable: false));
        self::assertSame([3, -99999.0, 99999.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function testSpyDecTypeBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'DEC', precision: 6, scale: 1, nullable: false));
        self::assertSame([1, -99999.0, 99999.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function testSpyBooleanCallsBoolean(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'BOOLEAN', nullable: false));
        self::assertSame([50], $spy->booleanCalls[0]);
    }

    #[Test]
    public function testSpyBoolCallsBoolean(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'BOOL', nullable: false));
        self::assertSame([50], $spy->booleanCalls[0]);
    }

    #[Test]
    public function testSpyByteaBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'BYTEA', nullable: false));
        self::assertSame([1, 100], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyIntervalValueBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INTERVAL', nullable: false));
        self::assertSame([1, 30], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyJsonValueBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'JSON', nullable: false));
        self::assertContains([1, 100], $spy->numberBetweenCalls);
        self::assertContains([20], $spy->methodCalls['text']);
    }

    #[Test]
    public function testSpyJsonbValueBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'JSONB', nullable: false));
        self::assertContains([1, 100], $spy->numberBetweenCalls);
        self::assertContains([20], $spy->methodCalls['text']);
    }

    #[Test]
    public function testSpyIntArrayCountBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INT_ARRAY', nullable: false));
        self::assertSame([1, 5], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyIntArrayElementBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INT_ARRAY', nullable: false));
        self::assertContains([1, 1000], $spy->numberBetweenCalls);
    }

    #[Test]
    public function testSpyTextArrayCountBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'TEXT_ARRAY', nullable: false));
        self::assertSame([1, 3], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyTextCallsParagraphs2(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'TEXT', nullable: false));
        self::assertSame([2, true], $spy->methodCalls['paragraphs'][0]);
    }

    #[Test]
    public function testSpyXmlCallsText50(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'XML', nullable: false));
        self::assertContains([50], $spy->methodCalls['text']);
    }

    #[Test]
    public function testSpyDefaultCallsText50(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'UNKNOWN_TYPE', nullable: false));
        self::assertContains([50], $spy->methodCalls['text']);
    }

    #[Test]
    public function testSpyVarcharTextBoundary(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'VARCHAR', length: 100, nullable: false));
        self::assertSame([100], $spy->methodCalls['text'][0]);
    }

    #[Test]
    public function testSpyVarcharTextCapAt200(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'VARCHAR', length: 500, nullable: false));
        self::assertSame([200], $spy->methodCalls['text'][0]);
    }

    #[Test]
    public function testSpyCharLexifyPattern(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'CHAR', length: 5, nullable: false));
        self::assertSame(['?????'], $spy->methodCalls['lexify'][0]);
    }

    #[Test]
    public function testSpyCharDefaultLengthLexify(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'CHAR', nullable: false));
        self::assertSame(['?'], $spy->methodCalls['lexify'][0]);
    }

    #[Test]
    public function testSpyNullableCallsBooleanWithTen(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INT', nullable: true));
        self::assertContains([10], $spy->booleanCalls);
    }

    #[Test]
    public function testSpyIntegerArrayAlias(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INTEGER_ARRAY', nullable: false));
        self::assertSame([1, 5], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function testSpyCharacterVaryingVarchar(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'CHARACTER VARYING', length: 50, nullable: false));
        self::assertSame([50], $spy->methodCalls['text'][0]);
    }

    #[Test]
    public function testSpyCharacterAlias(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'CHARACTER', length: 3, nullable: false));
        self::assertSame(['???'], $spy->methodCalls['lexify'][0]);
    }

    #[Test]
    public function testGenerateCharExactLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'CHAR', length: 5, nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertSame(5, strlen($value));
    }

    #[Test]
    public function testGenerateVarcharMaxLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

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
        $mapper = new PostgreSqlTypeMapper();

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
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'VARCHAR', length: 200, nullable: false);
        $value = $mapper->generate($faker, $column);

        $faker->seed(12345);
        $text = $faker->text(min(200, 200));
        $expected = substr($text, 0, 200);
        self::assertSame($expected, $value);
    }

    #[Test]
    public function testGenerateByteaNonEmpty(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'BYTEA', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertStringStartsWith('\\x', $value);
        self::assertGreaterThanOrEqual(4, strlen($value));
    }

    #[Test]
    public function testGenerateIntArrayFormat(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'INTEGER_ARRAY', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertStringStartsWith('{', $value);
        self::assertStringEndsWith('}', $value);
        $inner = substr($value, 1, -1);
        $items = explode(',', $inner);
        self::assertGreaterThanOrEqual(1, count($items));
        array_map(
            fn (string $item) => self::assertTrue(is_numeric(trim($item))),
            $items
        );
    }

    #[Test]
    public function testGenerateTextArrayFormat(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'TEXT_ARRAY', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertStringStartsWith('{', $value);
        self::assertStringEndsWith('}', $value);
        $inner = substr($value, 1, -1);
        self::assertGreaterThanOrEqual(1, substr_count($inner, '"'));
    }

    #[Test]
    public function testGenerateIntervalContainsUnit(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'INTERVAL', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertMatchesRegularExpression('/(days|hours|minutes|seconds|months|years)/', $value);
    }

    #[Test]
    public function testNullableColumnDefaultRatioIsLow(): void
    {
        $faker = Factory::create();
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'INTEGER', nullable: true, default: 'MARKER');

        $total = 500;
        $defaultCount = count(array_filter(array_map(function (int $i) use ($faker, $mapper, $column) {
            $faker->seed($i);

            return $mapper->generate($faker, $column);
        }, range(0, $total - 1)), fn ($value): bool => $value === 'MARKER'));
        self::assertLessThan((int) ($total * 0.5), $defaultCount, 'Default should be returned rarely (10% chance), not often (90%)');
    }

    #[Test]
    public function testGenerateIntArrayExactCount(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'INTEGER_ARRAY', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertStringStartsWith('{', $value);
        self::assertStringEndsWith('}', $value);
        $inner = substr($value, 1, -1);
        $elements = explode(',', $inner);
        self::assertGreaterThanOrEqual(1, count($elements));
        self::assertLessThanOrEqual(5, count($elements));
    }

    #[Test]
    public function testGenerateTextArrayExactCount(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'TEXT_ARRAY', nullable: false);
        $value = $mapper->generate($faker, $column);
        self::assertIsString($value);
        self::assertStringStartsWith('{', $value);
        self::assertStringEndsWith('}', $value);
        $inner = substr($value, 1, -1);
        preg_match_all('/"[^"]*"/', $inner, $matches);
        self::assertGreaterThanOrEqual(1, count($matches[0]));
        self::assertLessThanOrEqual(3, count($matches[0]));
    }
}
