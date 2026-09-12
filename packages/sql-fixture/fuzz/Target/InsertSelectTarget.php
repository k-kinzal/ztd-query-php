<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use Faker\Factory;
use Faker\Generator;
use JsonException;
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

    /**
     * Initializes the collaborators and declared state for this object.
     */
    public function __construct(
        private readonly PDO $pdo,
    ) {
        $this->faker = Factory::create();
        $this->fixtureProvider = new FixtureProvider($this->faker);

        $this->pdo->exec(str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', self::ALL_TYPES_TABLE));
    }

    /**
     * Fuzz target callable.
     *
     * @param string $input Raw fuzzer input (mutated bytes)
     * @throws Error On INSERT/SELECT mismatch
     * @throws JsonException When a round-tripped JSON value is malformed
     */
    public function __invoke(string $input): void
    {
        $seed = crc32(str_pad($input, 4, "\0"));
        $this->faker->seed($seed);

        $fixture = $this->fixtureProvider->fixture(self::ALL_TYPES_TABLE);

        $columns = array_keys($fixture);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            'INSERT INTO all_types (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare($sql);
            foreach (array_values($fixture) as $index => $value) {
                $type = match (true) {
                    $value === null => PDO::PARAM_NULL,
                    is_int($value) => PDO::PARAM_INT,
                    is_bool($value) => PDO::PARAM_BOOL,
                    default => PDO::PARAM_STR,
                };
                $stmt->bindValue($index + 1, $value, $type);
            }
            $stmt->execute();

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
                $actual = $result[$column] ?? null;

                if (!(new \Fuzz\Oracle\StoredValueComparator())->compare($expected, $actual, $column)) {
                    throw new Error(
                        "Value mismatch\n" .
                        "Seed: $seed\n" .
                        "Column: $column\n" .
                        'Expected: ' . var_export($expected, true) . "\n" .
                        'Actual: ' . var_export($actual, true)
                    );
                }
            }

        } finally {
            $this->pdo->rollBack();
        }
    }




}
