<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSql;

use Faker\Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlFixture\FixtureProvider;
use SqlFixture\Platform\PlatformFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use SqlFixture\FixtureGenerator;
use SqlFixture\Platform\PostgreSql\PostgreSqlSchemaParser;
use SqlFixture\Platform\PostgreSql\PostgreSqlTypeMapper;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TableSchema;

#[CoversClass(FixtureProvider::class)]
#[UsesClass(FixtureGenerator::class)]
#[UsesClass(PlatformFactory::class)]
#[UsesClass(PostgreSqlSchemaParser::class)]
#[UsesClass(PostgreSqlTypeMapper::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(TableSchema::class)]
final class PostgreSqlEndToEndTest extends TestCase
{
    #[Test]
    public function fixtureWithCommonTypes(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new FixtureProvider($faker, dialect: PlatformFactory::DRIVER_PGSQL);
        $data = $provider->fixture(<<<'SQL'
            CREATE TABLE users (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email TEXT NOT NULL,
                age INTEGER,
                balance NUMERIC(10, 2),
                active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
            )
            SQL);
        self::assertArrayNotHasKey('id', $data);
        self::assertArrayHasKey('name', $data);
        self::assertIsString($data['name']);
        self::assertLessThanOrEqual(100, strlen($data['name']));
        self::assertArrayHasKey('email', $data);
        self::assertIsString($data['email']);
    }

    #[Test]
    public function fixtureWithPgSpecificTypes(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new FixtureProvider($faker, dialect: PlatformFactory::DRIVER_PGSQL);
        $data = $provider->fixture(<<<'SQL'
            CREATE TABLE pg_types (
                col_uuid UUID NOT NULL,
                col_jsonb JSONB NOT NULL,
                col_json JSON NOT NULL,
                col_bytea BYTEA NOT NULL,
                col_inet INET NOT NULL,
                col_cidr CIDR NOT NULL,
                col_macaddr MACADDR NOT NULL,
                col_money MONEY NOT NULL,
                col_interval INTERVAL NOT NULL,
                col_xml XML NOT NULL
            )
            SQL);
        $colUuid = $data['col_uuid'];
        self::assertIsString($colUuid);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $colUuid
        );
        $colJsonb = $data['col_jsonb'];
        self::assertIsString($colJsonb);
        $decoded = json_decode($colJsonb, true);
        self::assertIsArray($decoded);
        $colJson = $data['col_json'];
        self::assertIsString($colJson);
        $decoded = json_decode($colJson, true);
        self::assertIsArray($decoded);
        $colBytea = $data['col_bytea'];
        self::assertIsString($colBytea);
        self::assertStringStartsWith('\\x', $colBytea);
        $colInet = $data['col_inet'];
        self::assertIsString($colInet);
        self::assertNotFalse(filter_var($colInet, FILTER_VALIDATE_IP));
        $colCidr = $data['col_cidr'];
        self::assertIsString($colCidr);
        self::assertStringContainsString('/24', $colCidr);
        $colMacaddr = $data['col_macaddr'];
        self::assertIsString($colMacaddr);
        self::assertMatchesRegularExpression('/^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$/', $colMacaddr);
        self::assertIsFloat($data['col_money']);
        self::assertIsString($data['col_interval']);
        $colXml = $data['col_xml'];
        self::assertIsString($colXml);
        self::assertStringStartsWith('<root>', $colXml);
    }

    #[Test]
    public function fixtureWithSerialTypes(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new FixtureProvider($faker, dialect: PlatformFactory::DRIVER_PGSQL);
        $data = $provider->fixture(<<<'SQL'
            CREATE TABLE serials (
                id SERIAL PRIMARY KEY,
                big_id BIGSERIAL NOT NULL,
                small_id SMALLSERIAL NOT NULL,
                name TEXT NOT NULL
            )
            SQL);

        self::assertArrayNotHasKey('id', $data);
        self::assertArrayNotHasKey('big_id', $data);
        self::assertArrayNotHasKey('small_id', $data);
        self::assertArrayHasKey('name', $data);
    }

    #[Test]
    public function fixtureWithTimestampVariants(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new FixtureProvider($faker, dialect: PlatformFactory::DRIVER_PGSQL);
        $data = $provider->fixture(<<<'SQL'
            CREATE TABLE times (
                col_ts TIMESTAMP NOT NULL,
                col_tstz TIMESTAMPTZ NOT NULL,
                col_date DATE NOT NULL,
                col_time TIME NOT NULL,
                col_timetz TIMETZ NOT NULL
            )
            SQL);

        $colTs = $data['col_ts'];
        self::assertIsString($colTs);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $colTs);
        self::assertIsString($data['col_tstz']);
        $colDate = $data['col_date'];
        self::assertIsString($colDate);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $colDate);
        $colTime = $data['col_time'];
        self::assertIsString($colTime);
        self::assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', $colTime);
        self::assertIsString($data['col_timetz']);
    }

    #[Test]
    public function fixtureWithArrayTypes(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new FixtureProvider($faker, dialect: PlatformFactory::DRIVER_PGSQL);
        $data = $provider->fixture(<<<'SQL'
            CREATE TABLE arrays (
                tags TEXT[] NOT NULL,
                ids INTEGER[] NOT NULL
            )
            SQL);

        $tags = $data['tags'];
        self::assertIsString($tags);
        self::assertStringStartsWith('{', $tags);
        self::assertStringEndsWith('}', $tags);

        $ids = $data['ids'];
        self::assertIsString($ids);
        self::assertStringStartsWith('{', $ids);
        self::assertStringEndsWith('}', $ids);
    }

    #[Test]
    public function fixtureWithOverrides(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new FixtureProvider($faker, dialect: PlatformFactory::DRIVER_PGSQL);
        $data = $provider->fixture(
            'CREATE TABLE test (id SERIAL PRIMARY KEY, name TEXT NOT NULL)',
            ['id' => 42, 'name' => 'Custom'],
        );

        self::assertSame(42, $data['id']);
        self::assertSame('Custom', $data['name']);
    }

    #[Test]
    public function fixtureWithGeneratedColumn(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new FixtureProvider($faker, dialect: PlatformFactory::DRIVER_PGSQL);
        $data = $provider->fixture(<<<'SQL'
            CREATE TABLE test (
                a INTEGER NOT NULL,
                b INTEGER NOT NULL,
                c INTEGER GENERATED ALWAYS AS (a + b) STORED
            )
            SQL);

        self::assertArrayHasKey('a', $data);
        self::assertArrayHasKey('b', $data);
        self::assertArrayNotHasKey('c', $data);
    }

    #[Test]
    public function fixtureWithNumericPrecision(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new FixtureProvider($faker, dialect: PlatformFactory::DRIVER_PGSQL);
        $data = $provider->fixture(<<<'SQL'
            CREATE TABLE test (
                price NUMERIC(5, 2) NOT NULL,
                qty INTEGER NOT NULL,
                rate DOUBLE PRECISION NOT NULL
            )
            SQL);

        self::assertIsFloat($data['price']);
        self::assertGreaterThanOrEqual(-999.99, $data['price']);
        self::assertLessThanOrEqual(999.99, $data['price']);
        self::assertIsInt($data['qty']);
        self::assertIsFloat($data['rate']);
    }

    #[Test]
    public function fixtureReproducibleWithSeed(): void
    {
        $sql = 'CREATE TABLE test (name TEXT NOT NULL, age INTEGER NOT NULL)';

        $faker1 = Factory::create();
        $faker1->seed(99999);
        $provider1 = new FixtureProvider($faker1, dialect: PlatformFactory::DRIVER_PGSQL);
        $data1 = $provider1->fixture($sql);

        $faker2 = Factory::create();
        $faker2->seed(99999);
        $provider2 = new FixtureProvider($faker2, dialect: PlatformFactory::DRIVER_PGSQL);
        $data2 = $provider2->fixture($sql);

        self::assertSame($data1['name'], $data2['name']);
        self::assertSame($data1['age'], $data2['age']);
    }
}
