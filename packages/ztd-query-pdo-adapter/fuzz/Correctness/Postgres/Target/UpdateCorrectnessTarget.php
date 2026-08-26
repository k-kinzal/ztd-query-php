<?php

declare(strict_types=1);

namespace Fuzz\Correctness\Postgres\Target;

use Error;
use Faker\Generator;
use Fuzz\Correctness\Postgres\PgCorrectnessHarness;
use Fuzz\Correctness\Postgres\PgSchemaAwareSqlBuilder;
use Fuzz\Correctness\Postgres\PgSchemaPool;
use Fuzz\Correctness\ResultComparator;
use Fuzz\Correctness\SchemaDefinition;
use PDO;
use PDOException;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;

/**
 * @phpstan-import-type Row from StatementInterface
 */
final class UpdateCorrectnessTarget
{
    private PgCorrectnessHarness $harness;
    private ResultComparator $comparator;
    private PgSchemaAwareSqlBuilder $sqlBuilder;
    private Generator $faker;

    public function __construct(
        PgCorrectnessHarness $harness,
        PgSchemaAwareSqlBuilder $sqlBuilder,
        Generator $faker
    ) {
        $this->harness = $harness;
        $this->comparator = new ResultComparator();
        $this->sqlBuilder = $sqlBuilder;
        $this->faker = $faker;
    }

    /**
     * @throws Error
     */
    public function __invoke(string $input): void
    {
        $seed = crc32(str_pad($input, 4, "\0"));
        $this->faker->seed($seed);

        $schema = PgSchemaPool::random($this->faker);
        $this->harness->setup($schema, $seed);

        try {
            $sql = $this->sqlBuilder->buildUpdate($schema);

            $rawError = null;
            try {
                $this->harness->getRawPdo()->exec($sql);
            } catch (PDOException $e) {
                $rawError = $e;
            }

            try {
                $this->harness->getZtdPdo()->exec($sql);
            } catch (UnsupportedSqlException | UnknownSchemaException | DatabaseException | PDOException $e) {
                if ($rawError !== null) {
                    return;
                }
                throw new Error("ZTD UPDATE failed after native success\nSeed: $seed\nSQL: $sql", 0, $e);
            }

            if ($rawError !== null) {
                return;
            }

            $this->compareTableState($schema, $seed);
        } finally {
            $this->harness->teardown();
        }
    }

    /**
     * @throws Error
     */
    private function compareTableState(SchemaDefinition $schema, int $seed): void
    {
        $rawRows = $this->fetchAll($this->harness->getRawPdo(), $schema->name);

        $selectSql = sprintf('SELECT * FROM "%s"', str_replace('"', '""', $schema->name));
        $stmt = $this->harness->getZtdPdo()->query($selectSql);
        /** @var list<Row> $ztdRows */
        $ztdRows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        if (!$this->comparator->compareRows($rawRows, $ztdRows, $schema->primaryKeys, $schema->columnTypes)) {
            throw new Error(
                "UPDATE table state mismatch\n" .
                "Seed: $seed\n" .
                "Schema: {$schema->name}\n" .
                'Raw row count: ' . count($rawRows) . "\n" .
                'ZTD row count: ' . count($ztdRows)
            );
        }
    }

    /**
     * @return list<Row>
     */
    private function fetchAll(PDO $pdo, string $table): array
    {
        $stmt = $pdo->query(sprintf('SELECT * FROM "%s"', str_replace('"', '""', $table)));
        /** @var list<Row> $rows */
        $rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        return $rows;
    }
}
