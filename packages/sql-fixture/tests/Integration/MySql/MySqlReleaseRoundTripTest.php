<?php

declare(strict_types=1);

namespace Tests\Integration\MySql;

use Container\Endpoint;
use Container\MySql56Container;
use Container\MySql57Container;
use Container\MySql80Container;
use Container\MySql81Container;
use Container\MySql82Container;
use Container\MySql83Container;
use Container\MySql84Container;
use Container\MySql90Container;
use Container\MySql91Container;
use Container\MySqlContainer;
use Faker\Factory;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlFixture\Provider\DatabaseFixtureProvider;
use SqlFixture\Version\ServerVersion;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class MySqlReleaseRoundTripTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string<MySqlContainer>, int}>
     */
    public static function providerReleases(): iterable
    {
        $selected = getenv('SQL_FIXTURE_MYSQL_VERSION');
        $containers = [
            MySql56Container::class,
            MySql57Container::class,
            MySql80Container::class,
            MySql81Container::class,
            MySql82Container::class,
            MySql83Container::class,
            MySql84Container::class,
            MySql90Container::class,
            MySql91Container::class,
        ];
        foreach ($containers as $container) {
            $tag = $container::getGrammarVersion();
            if ($selected !== false && $selected !== '' && $tag !== 'mysql-' . $selected) {
                continue;
            }
            foreach ([1, 2, 3] as $seed) {
                yield $tag . ' seed ' . $seed => [$container, $seed];
            }
        }
    }

    /**
     * @param class-string<MySqlContainer> $container
     */
    #[DataProvider('providerReleases')]
    public function testRowGeneratedFromTheReportedSchemaIsAccepted(string $container, int $seed): void
    {
        $endpoint = Testcontainers::run($container)->getData(Endpoint::class);
        $pdo = new PDO($endpoint->dsn(), $endpoint->username, $endpoint->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $faker = Factory::create();
        $faker->seed($seed);
        $provider = new DatabaseFixtureProvider($faker, $pdo);
        self::assertSame($container::getGrammarVersion(), $provider->getVersion());

        $table = 'fixture_' . bin2hex(random_bytes(4));
        $json = ServerVersion::resolve('mysql', $provider->getVersion())->isAtLeast('5.7.8') ? 'col_json JSON NOT NULL,' : '';
        $pdo->exec(<<<SQL
            CREATE TABLE {$table} (
                id INT PRIMARY KEY AUTO_INCREMENT,
                col_tinyint TINYINT NOT NULL,
                col_tinyint_unsigned TINYINT UNSIGNED NOT NULL,
                col_smallint SMALLINT NOT NULL,
                col_mediumint MEDIUMINT NOT NULL,
                col_int INT NOT NULL,
                col_bigint BIGINT NOT NULL,
                col_bigint_unsigned BIGINT UNSIGNED NOT NULL,
                col_float FLOAT NOT NULL,
                col_double DOUBLE NOT NULL,
                col_decimal DECIMAL(10, 2) NOT NULL,
                col_bit BIT(8) NOT NULL,
                col_bool BOOLEAN NOT NULL,
                col_char CHAR(10) NOT NULL,
                col_varchar VARCHAR(255) NOT NULL,
                col_binary BINARY(8) NOT NULL,
                col_varbinary VARBINARY(32) NOT NULL,
                col_tinytext TINYTEXT NOT NULL,
                col_text TEXT NOT NULL,
                col_mediumtext MEDIUMTEXT NOT NULL,
                col_longtext LONGTEXT NOT NULL,
                col_tinyblob TINYBLOB NOT NULL,
                col_blob BLOB NOT NULL,
                col_enum ENUM('a', 'b', 'c') NOT NULL,
                col_set SET('x', 'y', 'z') NOT NULL,
                col_date DATE NOT NULL,
                col_time TIME NOT NULL,
                col_datetime DATETIME NOT NULL,
                col_timestamp TIMESTAMP NULL,
                col_year YEAR NOT NULL,
                col_nullable VARCHAR(20) NULL DEFAULT 'none',
                {$json}
                col_point POINT NOT NULL,
                col_polygon POLYGON NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
        try {
            $row = $provider->fixture($table);
            $expressions = ['col_bit' => 'CAST(? AS UNSIGNED)', 'col_point' => 'ST_GeomFromText(?)', 'col_polygon' => 'ST_GeomFromText(?)'];
            $statement = $pdo->prepare(sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $table,
                implode(', ', array_keys($row)),
                implode(', ', array_map(static fn (string $column): string => $expressions[$column] ?? '?', array_keys($row))),
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
