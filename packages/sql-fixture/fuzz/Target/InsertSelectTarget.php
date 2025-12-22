<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use Faker\Factory;
use Faker\Generator;
use PDO;
use SqlFixture\FixtureProvider;

/**
 * Fuzz target for INSERT/SELECT consistency validation.
 *
 * This target generates fixtures, inserts them into MySQL,
 * and validates the data can be retrieved correctly.
 */
final class InsertSelectTarget
{
    private const ALL_TYPES_TABLE = <<<'SQL'
        CREATE TABLE all_types (
            id INT PRIMARY KEY AUTO_INCREMENT,
            col_tinyint TINYINT,
            col_tinyint_unsigned TINYINT UNSIGNED,
            col_smallint SMALLINT,
            col_mediumint MEDIUMINT,
            col_int INT,
            col_bigint BIGINT,
            col_float FLOAT,
            col_double DOUBLE,
            col_decimal DECIMAL(10,2),
            col_bit BIT(8),
            col_char CHAR(10),
            col_varchar VARCHAR(255),
            col_tinytext TINYTEXT,
            col_text TEXT,
            col_enum ENUM('a','b','c'),
            col_set SET('x','y','z'),
            col_date DATE,
            col_time TIME,
            col_datetime DATETIME,
            col_timestamp TIMESTAMP NULL,
            col_year YEAR,
            col_json JSON
        )
        SQL;

    private Generator $faker;
    private FixtureProvider $fixtureProvider;

    public function __construct(
        private readonly PDO $pdo,
    ) {
        $this->faker = Factory::create();
        $this->fixtureProvider = new FixtureProvider($this->faker);

        $this->pdo->exec('DROP TABLE IF EXISTS all_types');
        $this->pdo->exec(self::ALL_TYPES_TABLE);
    }

    /**
     * Fuzz target callable.
     *
     * @param string $input Raw fuzzer input (mutated bytes)
     * @throws Error On INSERT/SELECT mismatch
     */
    public function __invoke(string $input): void
    {
        $seed = $this->inputToSeed($input);
        $this->faker->seed($seed);

        $fixture = $this->fixtureProvider->fixture(self::ALL_TYPES_TABLE);

        $columns = array_keys($fixture);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            'INSERT INTO all_types (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $values = array_map(function ($v) {
            if (is_bool($v)) {
                return $v ? 1 : 0;
            }
            return $v;
        }, array_values($fixture));

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);

        $id = (int) $this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare('SELECT * FROM all_types WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($result)) {
            throw new Error(
                "Failed to retrieve inserted row\n" .
                "Seed: $seed\n" .
                "ID: $id"
            );
        }

        foreach ($fixture as $column => $expected) {
            /** @var mixed $actual */
            $actual = $result[$column] ?? null;

            if (!$this->compare($expected, $actual, $column)) {
                throw new Error(
                    "Value mismatch\n" .
                    "Seed: $seed\n" .
                    "Column: $column\n" .
                    "Expected: " . var_export($expected, true) . "\n" .
                    "Actual: " . var_export($actual, true)
                );
            }
        }

        $this->pdo->exec("DELETE FROM all_types WHERE id = $id");
    }

    private function inputToSeed(string $input): int
    {
        if (strlen($input) < 4) {
            $input = str_pad($input, 4, "\0");
        }
        return crc32($input);
    }

    /**
     * Compare expected and actual values with type-appropriate logic.
     */
    private function compare(mixed $expected, mixed $actual, string $column): bool
    {
        if ($expected === null && $actual === null) {
            return true;
        }

        if ($expected === null || $actual === null) {
            return false;
        }

        if (is_bool($expected)) {
            return (bool) $actual === $expected;
        }

        if (is_float($expected)) {
            $actualFloat = (float) (is_numeric($actual) ? $actual : 0);
            if ($expected === 0.0) {
                return abs($actualFloat) < 0.0001;
            }
            return abs($expected - $actualFloat) / abs($expected) < 0.001;
        }

        if (is_int($expected)) {
            if (str_starts_with($column, 'col_bit')) {
                $actualStr = is_string($actual) ? $actual : '';
                $actualInt = $actualStr === '' ? 0 : ord($actualStr);
                return $expected === $actualInt;
            }
            return $expected === (int) (is_numeric($actual) ? $actual : 0);
        }

        if (is_string($expected)) {
            $actualStr = is_string($actual) ? $actual : (is_scalar($actual) ? (string) $actual : '');
            if ($column === 'col_json') {
                $expectedJson = json_decode($expected, true);
                $actualJson = json_decode($actualStr, true);
                return $expectedJson === $actualJson;
            }

            if ($column === 'col_set') {
                $expectedParts = explode(',', $expected);
                $actualParts = explode(',', $actualStr);
                sort($expectedParts);
                sort($actualParts);
                return $expectedParts === $actualParts;
            }

            return $expected === $actual;
        }

        return $expected === $actual;
    }
}
