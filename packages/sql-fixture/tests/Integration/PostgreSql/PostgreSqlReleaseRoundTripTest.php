<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSql;

use Container\Endpoint;
use Container\PostgreSql17Container;
use Faker\Factory;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlFixture\Provider\DatabaseFixtureProvider;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class PostgreSqlReleaseRoundTripTest extends TestCase
{
    /**
     * @return iterable<string, array{int}>
     */
    public static function providerSeeds(): iterable
    {
        yield 'seed 1' => [1];
        yield 'seed 2' => [2];
        yield 'seed 3' => [3];
    }

    #[DataProvider('providerSeeds')]
    public function testRowGeneratedFromTheReportedSchemaIsAccepted(int $seed): void
    {
        $endpoint = Testcontainers::run(PostgreSql17Container::class)->getData(Endpoint::class);
        $pdo = new PDO($endpoint->dsn(), $endpoint->username, $endpoint->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $faker = Factory::create();
        $faker->seed($seed);
        $provider = new DatabaseFixtureProvider($faker, $pdo);
        self::assertSame('pg-17.2', $provider->getVersion());

        $table = 'fixture_' . bin2hex(random_bytes(4));
        $pdo->exec(<<<SQL
            CREATE TABLE {$table} (
                id SERIAL PRIMARY KEY,
                col_smallint SMALLINT NOT NULL,
                col_integer INTEGER NOT NULL,
                col_bigint BIGINT NOT NULL,
                col_numeric NUMERIC(10, 2) NOT NULL,
                col_real REAL NOT NULL,
                col_double DOUBLE PRECISION NOT NULL,
                col_boolean BOOLEAN NOT NULL,
                col_char CHAR(10) NOT NULL,
                col_varchar VARCHAR(100) NOT NULL,
                col_text TEXT NOT NULL,
                col_bytea BYTEA NOT NULL,
                col_date DATE NOT NULL,
                col_time TIME NOT NULL,
                col_timetz TIMETZ NOT NULL,
                col_timestamp TIMESTAMP NOT NULL,
                col_timestamptz TIMESTAMPTZ NOT NULL,
                col_interval INTERVAL NOT NULL,
                col_uuid UUID NOT NULL,
                col_json JSON NOT NULL,
                col_jsonb JSONB NOT NULL,
                col_inet INET NOT NULL,
                col_cidr CIDR NOT NULL,
                col_macaddr MACADDR NOT NULL,
                col_money MONEY NOT NULL,
                col_xml XML NOT NULL,
                col_text_array TEXT[] NOT NULL,
                col_int_array INTEGER[] NOT NULL,
                col_nullable VARCHAR(20) DEFAULT 'none'
            )
            SQL);
        try {
            $row = $provider->fixture($table);
            $statement = $pdo->prepare(sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $table,
                implode(', ', array_keys($row)),
                implode(', ', array_fill(0, count($row), '?')),
            ));
            self::assertTrue($statement->execute(array_values(array_replace($row, array_map('intval', array_filter($row, 'is_bool'))))));
            $count = $pdo->query(sprintf('SELECT COUNT(*) FROM %s', $table));
            self::assertNotFalse($count);
            self::assertSame(1, (int) $count->fetchColumn());
        } finally {
            $pdo->exec(sprintf('DROP TABLE IF EXISTS %s', $table));
        }
    }
}
