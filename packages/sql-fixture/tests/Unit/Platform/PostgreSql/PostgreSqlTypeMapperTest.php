<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql;

use Faker\Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\PostgreSqlTypeMapper;
use SqlFixture\Schema\ColumnDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Fixture\SpyGenerator;

#[CoversClass(PostgreSqlTypeMapper::class)]
#[UsesClass(ColumnDefinition::class)]
final class PostgreSqlTypeMapperTest extends TestCase
{
    #[Test]
    public function generateInteger(): void
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
    public function generateSmallInt(): void
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
    public function generateBigInt(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'BIGINT', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsInt($value);
    }

    #[Test]
    public function generateReal(): void
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
    public function generateDoublePrecision(): void
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
    public function generateNumeric(): void
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
    public function generateBoolean(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'BOOLEAN', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsBool($value);
    }

    #[Test]
    public function generateText(): void
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
    public function generateVarchar(): void
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
    public function generateChar(): void
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
    public function generateDate(): void
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
    public function generateTime(): void
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
    public function generateTimestamp(): void
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
    public function generateTimestamptz(): void
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
    public function generateUuid(): void
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
    public function generateJsonb(): void
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
    public function generateJson(): void
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
    public function generateBytea(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'BYTEA', nullable: false);
        /** @var string $value */
        $value = $mapper->generate($faker, $column);
        self::assertStringStartsWith('\\x', $value);
        self::assertGreaterThan(2, strlen($value));
    }

    #[Test]
    public function generateInet(): void
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
    public function generateCidr(): void
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
    public function generateMacaddr(): void
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
    public function generateMoney(): void
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
    public function generateInterval(): void
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
    public function generateIntegerArray(): void
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
    public function generateTextArray(): void
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
    public function generateAutoIncrementReturnsNull(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('id', 'INTEGER', autoIncrement: true, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertNull($value);
    }

    #[Test]
    public function generateGeneratedColumnReturnsNull(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('computed', 'INTEGER', generated: true, nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertNull($value);
    }

    #[Test]
    public function generateNullable(): void
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
    public function generateNonNullableNeverReturnsNull(): void
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
    public function generateDecimalBoundaryValues(): void
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
    public function generateSmallIntBoundaryValues(): void
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
    public function generateMoneyBoundaryValues(): void
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
    public function generateUuidFormat(): void
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
    public function generateInetFormat(): void
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
    public function generateCidrFormat(): void
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
    public function generateMacaddrFormat(): void
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
    public function generateIntervalFormat(): void
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
    public function generateByteaFormat(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();

        $column = new ColumnDefinition('col', 'BYTEA', nullable: false);
        /** @var string $value */
        $value = $mapper->generate($faker, $column);
        self::assertStringStartsWith('\\x', $value);
        self::assertGreaterThan(2, strlen($value));
        self::assertMatchesRegularExpression('/^\\\\x[0-9a-f]+$/', $value);
    }

    #[Test]
    public function generateXmlFormat(): void
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
    public function generateTimetzFormat(): void
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
    public function generateCharacterType(): void
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
    public function generateCharacterVaryingType(): void
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
    public function generateXml(): void
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
    public function generateInt2Alias(): void
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
    public function generateInt4Alias(): void
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
    public function generateInt8Alias(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'INT8', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsInt($value);
    }

    #[Test]
    public function generateFloat4Alias(): void
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
    public function generateFloat8Alias(): void
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
    public function generateBoolAlias(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'BOOL', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsBool($value);
    }

    #[Test]
    public function generateDecAlias(): void
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
    public function generateTimeWithoutTimeZone(): void
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
    public function generateTimeWithTimeZone(): void
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
    public function generateTimestampWithoutTimeZone(): void
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
    public function generateTimestampWithTimeZone(): void
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
    public function generateIntArrayAlias(): void
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
    public function generateDecimalDefaultPrecision(): void
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
    public function generateCharDefaultLength(): void
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
    public function generateVarcharDefaultLength(): void
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
    public function generateUnknownTypeReturnsText(): void
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
    public function generateLowercaseTypeWorks(): void
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
    public function generateJsonHasKeyValue(): void
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
    public function generateIntervalValueRange(): void
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
    public function generateIntArrayContainsNumbers(): void
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
    public function generateTextArrayContainsQuotedStrings(): void
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
    public function snapshotSmallInt(): void
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
    public function snapshotInt2(): void
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
    public function snapshotInteger(): void
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
    public function snapshotInt(): void
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
    public function snapshotInt4(): void
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
    public function snapshotBigInt(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'BIGINT', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsInt($value);
    }

    #[Test]
    public function snapshotInt8(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'INT8', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsInt($value);
    }

    #[Test]
    public function snapshotReal(): void
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
    public function snapshotFloat4(): void
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
    public function snapshotDoublePrecision(): void
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
    public function snapshotFloat8(): void
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
    public function snapshotDecimal(): void
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
    public function snapshotNumeric(): void
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
    public function snapshotDec(): void
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
    public function snapshotMoney(): void
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
    public function snapshotBoolean(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'BOOLEAN', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsBool($value);
    }

    #[Test]
    public function snapshotBool(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'BOOL', nullable: false);
        $value = $mapper->generate($faker, $column);

        self::assertIsBool($value);
    }

    #[Test]
    public function snapshotChar(): void
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
    public function snapshotCharacter(): void
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
    public function snapshotDate(): void
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
    public function snapshotTime(): void
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
    public function snapshotTimeWithoutTimeZone(): void
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
    public function snapshotTimeWithTimeZone(): void
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
    public function snapshotTimetz(): void
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
    public function snapshotTimestamp(): void
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
    public function snapshotTimestampWithoutTimeZone(): void
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
    public function snapshotTimestampWithTimeZone(): void
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
    public function snapshotTimestamptz(): void
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
    public function snapshotInterval(): void
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
    public function snapshotJson(): void
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
    public function snapshotJsonb(): void
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
    public function snapshotUuid(): void
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
    public function snapshotInet(): void
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
    public function snapshotCidr(): void
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
    public function snapshotMacaddr(): void
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
    public function snapshotIntegerArray(): void
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
    public function snapshotIntArray(): void
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
    public function snapshotTextArray(): void
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
    public function snapshotXml(): void
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
    public function snapshotBytea(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'BYTEA', nullable: false);
        /** @var string $value */
        $value = $mapper->generate($faker, $column);
        self::assertStringStartsWith('\\x', $value);
        self::assertGreaterThan(2, strlen($value));
    }

    #[Test]
    public function snapshotUnknownType(): void
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
    public function snapshotDecimalDefaultPrecisionExact(): void
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
    public function snapshotDecimalWithPrecisionExact(): void
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
    public function snapshotNumericDefaultPrecision(): void
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
    public function snapshotDecExact(): void
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
    public function snapshotCharExact(): void
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
    public function snapshotCharDefaultLength(): void
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
    public function snapshotVarcharExact(): void
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
    public function snapshotVarcharDefaultLength(): void
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
    public function snapshotCharacterVarying(): void
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
    public function snapshotByteaFormat(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'BYTEA', nullable: false);
        /** @var string $value */
        $value = $mapper->generate($faker, $column);
        self::assertStringStartsWith('\\x', $value);
        $hexPart = substr($value, 2);
        self::assertSame(0, strlen($hexPart) % 2);
        self::assertMatchesRegularExpression('/^[0-9a-f]+$/', $hexPart);
    }

    #[Test]
    public function snapshotIntegerArrayExact(): void
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
    public function snapshotTextArrayExact(): void
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
    public function snapshotXmlExact(): void
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
    public function snapshotIntervalExact(): void
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
    public function snapshotMoneyExact(): void
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
    public function nullableColumnReturnsDefault(): void
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
    public function nullableColumnReturnsDefaultWithSpecificSeed(): void
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
    public function nullableColumnReturnsGeneratedWithSpecificSeed(): void
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
    public function nullableColumnSeed28ReturnsDefault(): void
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
    public function nullableColumnSeed285ReturnsGenerated(): void
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
    public function generateCharacterVaryingShortLength(): void
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
    public function generateTextArraySeed5Has3Elements(): void
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
    public function spySmallIntBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'SMALLINT', nullable: false));
        self::assertSame([-32768, 32767], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyInt2Boundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INT2', nullable: false));
        self::assertSame([-32768, 32767], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyIntegerBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INTEGER', nullable: false));
        self::assertSame([-2147483648, 2147483647], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyIntBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INT', nullable: false));
        self::assertSame([-2147483648, 2147483647], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyInt4Boundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INT4', nullable: false));
        self::assertSame([-2147483648, 2147483647], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyBigIntBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'BIGINT', nullable: false));
        self::assertSame([PHP_INT_MIN, PHP_INT_MAX], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyInt8Boundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INT8', nullable: false));
        self::assertSame([PHP_INT_MIN, PHP_INT_MAX], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyRealBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'REAL', nullable: false));
        self::assertSame([2, -1000.0, 1000.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function spyFloat4Boundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'FLOAT4', nullable: false));
        self::assertSame([2, -1000.0, 1000.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function spyDoublePrecisionBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'DOUBLE PRECISION', nullable: false));
        self::assertSame([4, -1000000.0, 1000000.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function spyFloat8Boundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'FLOAT8', nullable: false));
        self::assertSame([4, -1000000.0, 1000000.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function spyMoneyBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'MONEY', nullable: false));
        self::assertSame([2, 0.0, 99999.99], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function spyDecimalBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'DECIMAL', precision: 5, scale: 2, nullable: false));
        self::assertSame([2, -999.0, 999.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function spyDecimalDefaultPrecision(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'DECIMAL', nullable: false));
        self::assertSame([0, -9999999999.0, 9999999999.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function spyNumericBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'NUMERIC', precision: 8, scale: 3, nullable: false));
        self::assertSame([3, -99999.0, 99999.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function spyDecTypeBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'DEC', precision: 6, scale: 1, nullable: false));
        self::assertSame([1, -99999.0, 99999.0], $spy->randomFloatCalls[0]);
    }

    #[Test]
    public function spyBooleanCallsBoolean(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'BOOLEAN', nullable: false));
        self::assertSame([50], $spy->booleanCalls[0]);
    }

    #[Test]
    public function spyBoolCallsBoolean(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'BOOL', nullable: false));
        self::assertSame([50], $spy->booleanCalls[0]);
    }

    #[Test]
    public function spyByteaBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'BYTEA', nullable: false));
        self::assertSame([1, 100], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyIntervalValueBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INTERVAL', nullable: false));
        self::assertSame([1, 30], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyJsonValueBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'JSON', nullable: false));
        self::assertContains([1, 100], $spy->numberBetweenCalls);
        self::assertContains([20], $spy->methodCalls['text']);
    }

    #[Test]
    public function spyJsonbValueBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'JSONB', nullable: false));
        self::assertContains([1, 100], $spy->numberBetweenCalls);
        self::assertContains([20], $spy->methodCalls['text']);
    }

    #[Test]
    public function spyIntArrayCountBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INT_ARRAY', nullable: false));
        self::assertSame([1, 5], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyIntArrayElementBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INT_ARRAY', nullable: false));
        self::assertContains([1, 1000], $spy->numberBetweenCalls);
    }

    #[Test]
    public function spyTextArrayCountBoundaries(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'TEXT_ARRAY', nullable: false));
        self::assertSame([1, 3], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyTextCallsParagraphs2(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'TEXT', nullable: false));
        self::assertSame([2, true], $spy->methodCalls['paragraphs'][0]);
    }

    #[Test]
    public function spyXmlCallsText50(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'XML', nullable: false));
        self::assertContains([50], $spy->methodCalls['text']);
    }

    #[Test]
    public function spyDefaultCallsText50(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'UNKNOWN_TYPE', nullable: false));
        self::assertContains([50], $spy->methodCalls['text']);
    }

    #[Test]
    public function spyVarcharTextBoundary(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'VARCHAR', length: 100, nullable: false));
        self::assertSame([100], $spy->methodCalls['text'][0]);
    }

    #[Test]
    public function spyVarcharTextCapAt200(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'VARCHAR', length: 500, nullable: false));
        self::assertSame([200], $spy->methodCalls['text'][0]);
    }

    #[Test]
    public function spyCharLexifyPattern(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'CHAR', length: 5, nullable: false));
        self::assertSame(['?????'], $spy->methodCalls['lexify'][0]);
    }

    #[Test]
    public function spyCharDefaultLengthLexify(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'CHAR', nullable: false));
        self::assertSame(['?'], $spy->methodCalls['lexify'][0]);
    }

    #[Test]
    public function spyNullableCallsBooleanWithTen(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INT', nullable: true));
        self::assertContains([10], $spy->booleanCalls);
    }

    #[Test]
    public function spyIntegerArrayAlias(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'INTEGER_ARRAY', nullable: false));
        self::assertSame([1, 5], $spy->numberBetweenCalls[0]);
    }

    #[Test]
    public function spyCharacterVaryingVarchar(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'CHARACTER VARYING', length: 50, nullable: false));
        self::assertSame([50], $spy->methodCalls['text'][0]);
    }

    #[Test]
    public function spyCharacterAlias(): void
    {
        $spy = SpyGenerator::create();
        $mapper = new PostgreSqlTypeMapper();
        $mapper->generate($spy, new ColumnDefinition('col', 'CHARACTER', length: 3, nullable: false));
        self::assertSame(['???'], $spy->methodCalls['lexify'][0]);
    }

    #[Test]
    public function generateCharExactLength(): void
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
    public function generateVarcharMaxLength(): void
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
    public function generateVarcharDefaultLengthOutput(): void
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
    public function generateVarcharStartsFromBeginning(): void
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
    public function generateByteaNonEmpty(): void
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
    public function generateIntArrayFormat(): void
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
    public function generateTextArrayFormat(): void
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
    public function generateIntervalContainsUnit(): void
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
    public function nullableColumnDefaultRatioIsLow(): void
    {
        $faker = Factory::create();
        $mapper = new PostgreSqlTypeMapper();
        $column = new ColumnDefinition('col', 'INTEGER', nullable: true, default: 'MARKER');

        $total = 500;
        $defaultCount = count(array_filter(array_map(function (int $i) use ($faker, $mapper, $column): mixed {
            $faker->seed($i);

            return $mapper->generate($faker, $column);
        }, range(0, $total - 1)), fn (mixed $value): bool => $value === 'MARKER'));
        self::assertLessThan((int) ($total * 0.5), $defaultCount, 'Default should be returned rarely (10% chance), not often (90%)');
    }

    #[Test]
    public function generateIntArrayExactCount(): void
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
    public function generateTextArrayExactCount(): void
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
