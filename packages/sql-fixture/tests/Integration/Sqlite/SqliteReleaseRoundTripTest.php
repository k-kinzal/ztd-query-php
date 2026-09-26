<?php

declare(strict_types=1);

namespace Tests\Integration\Sqlite;

use Faker\Factory;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlFixture\Provider\DatabaseFixtureProvider;

#[CoversNothing]
#[Medium]
final class SqliteReleaseRoundTripTest extends TestCase
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
        $pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $faker = Factory::create();
        $faker->seed($seed);
        $provider = new DatabaseFixtureProvider($faker, $pdo);
        self::assertSame('sqlite-3.47.2', $provider->getVersion());

        $pdo->exec(<<<'SQL'
            CREATE TABLE fixture_rows (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                col_int INT NOT NULL,
                col_tinyint TINYINT NOT NULL,
                col_smallint SMALLINT NOT NULL,
                col_mediumint MEDIUMINT NOT NULL,
                col_bigint BIGINT NOT NULL,
                col_text TEXT NOT NULL,
                col_varchar VARCHAR(100) NOT NULL,
                col_char CHAR(10) NOT NULL,
                col_real REAL NOT NULL,
                col_float FLOAT NOT NULL,
                col_double DOUBLE NOT NULL,
                col_decimal DECIMAL(10, 2) NOT NULL,
                col_blob BLOB NOT NULL,
                col_boolean BOOLEAN NOT NULL,
                col_date DATE NOT NULL,
                col_time TIME NOT NULL,
                col_datetime DATETIME NOT NULL,
                col_nullable VARCHAR(20) DEFAULT 'none'
            )
            SQL);
        $row = $provider->fixture('fixture_rows');
        $statement = $pdo->prepare(sprintf(
            'INSERT INTO fixture_rows (%s) VALUES (%s)',
            implode(', ', array_keys($row)),
            implode(', ', array_fill(0, count($row), '?')),
        ));
        self::assertTrue($statement->execute(array_values(array_replace($row, array_map('intval', array_filter($row, 'is_bool'))))));
        $count = $pdo->query('SELECT COUNT(*) FROM fixture_rows');
        self::assertNotFalse($count);
        self::assertSame(1, (int) $count->fetchColumn());
    }
}
