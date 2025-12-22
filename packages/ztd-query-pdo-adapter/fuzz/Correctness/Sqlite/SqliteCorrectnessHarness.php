<?php

declare(strict_types=1);

namespace Fuzz\Correctness\Sqlite;

use Faker\Factory;
use Faker\Generator;
use Fuzz\Correctness\SchemaDefinition;
use PDO;
use ZtdQuery\Adapter\Pdo\ZtdPdo;
use ZtdQuery\Config\UnknownSchemaBehavior;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;

final class SqliteCorrectnessHarness
{
    private PDO $rawPdo;
    private ?ZtdPdo $ztdPdo = null;
    private ?SchemaDefinition $currentSchema = null;
    private Generator $faker;

    /** @var array<int, array<string, mixed>> */
    private array $fixtureRows = [];

    public function __construct()
    {
        $this->rawPdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->faker = Factory::create();
    }

    /**
     * Set up both connections with the same schema and data.
     *
     * @return array<int, array<string, mixed>> The fixture rows inserted
     */
    public function setup(SchemaDefinition $schema, int $seed, int $rowCount = 3): array
    {
        $this->currentSchema = $schema;
        $this->faker->seed($seed);

        $this->rawPdo->exec("DROP TABLE IF EXISTS \"{$schema->name}\"");
        $this->rawPdo->exec($schema->sql);

        $this->fixtureRows = [];
        for ($i = 0; $i < $rowCount; $i++) {
            $row = $this->generateFixtureRow($schema, $i);
            $this->fixtureRows[] = $row;
        }

        foreach ($this->fixtureRows as $row) {
            $this->insertRow($this->rawPdo, $schema->name, $row);
        }

        $this->ztdPdo = ZtdPdo::fromPdo(
            $this->rawPdo,
            new ZtdConfig(UnsupportedSqlBehavior::Ignore, UnknownSchemaBehavior::Exception)
        );

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
                    return $v ? '1' : '0';
                }
                return "'" . str_replace("'", "''", is_scalar($v) ? $v : '') . "'";
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

    public function teardown(): void
    {
        if ($this->currentSchema !== null) {
            $this->rawPdo->exec("DROP TABLE IF EXISTS \"{$this->currentSchema->name}\"");
        }
        $this->ztdPdo = null;
        $this->currentSchema = null;
        $this->fixtureRows = [];
    }

    public function getRawPdo(): PDO
    {
        return $this->rawPdo;
    }

    public function getZtdPdo(): ZtdPdo
    {
        if ($this->ztdPdo === null) {
            throw new \RuntimeException('ZtdPdo not initialized. Call setup() first.');
        }
        return $this->ztdPdo;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFixtureRows(): array
    {
        return $this->fixtureRows;
    }

    public function getCurrentSchema(): ?SchemaDefinition
    {
        return $this->currentSchema;
    }

    /**
     * @return array<string, mixed>
     */
    private function generateFixtureRow(SchemaDefinition $schema, int $index): array
    {
        $row = [];
        foreach ($schema->columns as $col) {
            $colLower = strtolower($col);

            if ($col === 'id' || str_ends_with($colLower, '_id')) {
                $row[$col] = $index + 1;
            } elseif (str_contains($colLower, 'real') || str_contains($colLower, 'float') || str_contains($colLower, 'double')) {
                $row[$col] = round($this->faker->randomFloat(2, 0, 999), 2);
            } elseif (str_contains($colLower, 'int') || str_contains($colLower, 'quantity') || str_contains($colLower, 'numeric')) {
                $row[$col] = $this->faker->numberBetween(1, 100);
            } elseif (str_contains($colLower, 'blob')) {
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
     * @param array<string, mixed> $row
     */
    private function insertRow(PDO $pdo, string $table, array $row): void
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
