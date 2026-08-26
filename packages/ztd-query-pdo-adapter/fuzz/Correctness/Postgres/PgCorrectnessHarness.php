<?php

declare(strict_types=1);

namespace Fuzz\Correctness\Postgres;

use Faker\Factory;
use Faker\Generator;
use Fuzz\Correctness\SchemaDefinition;
use PDO;
use RuntimeException;
use ZtdQuery\Adapter\Pdo\ZtdPdo;
use ZtdQuery\Config\UnknownSchemaBehavior;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\StatementInterface;

/**
 * @phpstan-import-type Row from StatementInterface
 */
final class PgCorrectnessHarness
{
    private PDO $rawPdo;
    private ?ZtdPdo $ztdPdo = null;
    private ?SchemaDefinition $currentSchema = null;
    private string $dsn;
    private string $user;
    private string $pass;
    private Generator $faker;

    /** @var list<Row> */
    private array $fixtureRows = [];

    /**
     * Binds the instance to what it will work from.
     *
     * @param string $host
     * @param int $port
     * @param string $dbName
     * @param string $user
     * @param string $pass
     */
    public function __construct(string $host, int $port, string $dbName, string $user, string $pass)
    {
        $this->dsn = "pgsql:host=$host;port=$port;dbname=$dbName";
        $this->user = $user;
        $this->pass = $pass;
        $this->rawPdo = new PDO($this->dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->faker = Factory::create();
    }

    /**
     * Set up both connections with the same schema and data.
     *
     * @return list<Row> The fixture rows inserted
     */
    public function setup(SchemaDefinition $schema, int $seed, int $rowCount = 3): array
    {
        $this->currentSchema = $schema;
        $this->faker->seed($seed);

        $this->rawPdo->exec("DROP TABLE IF EXISTS \"{$schema->name}\" CASCADE");
        $this->rawPdo->exec($schema->sql);

        $this->fixtureRows = [];
        for ($i = 0; $i < $rowCount; $i++) {
            $row = $this->generateFixtureRow($schema, $i);
            $this->fixtureRows[] = $row;
        }

        foreach ($this->fixtureRows as $row) {
            $this->insertRow($this->rawPdo, $schema->name, $row);
        }

        $this->ztdPdo = new ZtdPdo($this->dsn, $this->user, $this->pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ], new ZtdConfig(UnsupportedSqlBehavior::Ignore, UnknownSchemaBehavior::Exception));

        $this->ztdPdo->exec($schema->sql);
        foreach ($this->fixtureRows as $row) {
            $columns = array_keys($row);
            $values = array_map(function ($v) {
                if ($v === null) {
                    return 'NULL';
                }
                if (is_int($v) || is_float($v)) {
                    return (string) $v;
                }
                if (is_bool($v)) {
                    return $v ? 'TRUE' : 'FALSE';
                }
                return "'" . str_replace("'", "''", $v) . "'";
            }, array_values($row));
            $sql = sprintf(
                'INSERT INTO "%s" (%s) VALUES (%s)',
                str_replace('"', '""', $schema->name),
                implode(', ', array_map(fn ($c) => '"' . str_replace('"', '""', $c) . '"', $columns)),
                implode(', ', $values)
            );
            $this->ztdPdo->exec($sql);
        }

        return $this->fixtureRows;
    }

    /**
     * Teardown.
     *
     */
    public function teardown(): void
    {
        if ($this->currentSchema !== null) {
            $this->rawPdo->exec("DROP TABLE IF EXISTS \"{$this->currentSchema->name}\" CASCADE");
        }
        $this->ztdPdo = null;
        $this->currentSchema = null;
        $this->fixtureRows = [];
    }

    /**
     * Answers raw pdo.
     *
     * @return PDO
     */
    public function getRawPdo(): PDO
    {
        return $this->rawPdo;
    }

    /**
     * @throws RuntimeException
     */
    public function getZtdPdo(): ZtdPdo
    {
        if ($this->ztdPdo === null) {
            throw new RuntimeException('ZtdPdo not initialized. Call setup() first.');
        }
        return $this->ztdPdo;
    }

    /**
     * @return list<Row>
     */
    public function getFixtureRows(): array
    {
        return $this->fixtureRows;
    }

    /**
     * Answers current schema.
     *
     * @return ?SchemaDefinition
     */
    public function getCurrentSchema(): ?SchemaDefinition
    {
        return $this->currentSchema;
    }

    /**
     * Answers one fixture row for the schema, made from the index so a run repeats.
     *
     * @param SchemaDefinition $schema The schema
     * @param int $index Where to read
     *
     * @return Row What it answers
     */
    public function generateFixtureRow(SchemaDefinition $schema, int $index): array
    {
        $row = [];
        foreach ($schema->columns as $col) {
            $colLower = strtolower($col);

            if ($col === 'id') {
                continue;
            }

            if (str_ends_with($colLower, '_id')) {
                $row[$col] = $index + 1;
            } elseif (str_contains($colLower, 'real') || str_contains($colLower, 'float') || str_contains($colLower, 'double')) {
                $row[$col] = round($this->faker->randomFloat(2, 0, 999), 2);
            } elseif (str_contains($colLower, 'bool')) {
                $row[$col] = $this->faker->boolean();
            } elseif (str_contains($colLower, 'smallint') || str_contains($colLower, 'int') || str_contains($colLower, 'quantity') || str_contains($colLower, 'bigint')) {
                $row[$col] = $this->faker->numberBetween(1, 100);
            } elseif (str_contains($colLower, 'numeric') || str_contains($colLower, 'decimal')) {
                $row[$col] = round($this->faker->randomFloat(2, 0, 9999), 2);
            } elseif (str_contains($colLower, 'char')) {
                $row[$col] = $this->faker->lexify('????');
            } else {
                $row[$col] = $this->faker->lexify('????');
            }
        }

        if ($schema->name === 'composite_pk') {
            $row['order_id'] = $index + 1;
            $row['product_id'] = ($index + 1) * 10;
        }

        return $row;
    }

    /**
     * Writes one fixture row into the table both sides read.
     *
     * @param PDO $pdo The pdo
     * @param string $table Table it belongs to
     * @param Row $row Row to read
     */
    public function insertRow(PDO $pdo, string $table, array $row): void
    {
        $columns = array_keys($row);
        $placeholders = array_fill(0, count($columns), '?');
        $sql = sprintf(
            'INSERT INTO "%s" (%s) VALUES (%s)',
            str_replace('"', '""', $table),
            implode(', ', array_map(fn ($c) => '"' . str_replace('"', '""', $c) . '"', $columns)),
            implode(', ', $placeholders)
        );
        $values = array_map(function ($v) {
            if (is_bool($v)) {
                return $v ? 1 : 0;
            }
            return $v;
        }, array_values($row));
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
    }
}
