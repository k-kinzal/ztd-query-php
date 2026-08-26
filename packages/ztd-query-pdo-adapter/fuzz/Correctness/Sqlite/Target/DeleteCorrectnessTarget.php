<?php

declare(strict_types=1);

namespace Fuzz\Correctness\Sqlite\Target;

use Error;
use Faker\Generator;
use Fuzz\Correctness\ResultComparator;
use Fuzz\Correctness\SchemaDefinition;
use Fuzz\Correctness\Sqlite\SqliteCorrectnessHarness;
use Fuzz\Correctness\Sqlite\SqliteSchemaAwareSqlBuilder;
use Fuzz\Correctness\Sqlite\SqliteSchemaPool;
use PDO;
use PDOException;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;

/**
 * @phpstan-import-type Row from StatementInterface
 */
final class DeleteCorrectnessTarget
{
    private SqliteCorrectnessHarness $harness;
    private ResultComparator $comparator;
    private SqliteSchemaAwareSqlBuilder $sqlBuilder;
    private Generator $faker;

    /**
     * Binds the instance to what it will work from.
     *
     * @param SqliteCorrectnessHarness $harness
     * @param SqliteSchemaAwareSqlBuilder $sqlBuilder
     * @param Generator $faker
     */
    public function __construct(
        SqliteCorrectnessHarness $harness,
        SqliteSchemaAwareSqlBuilder $sqlBuilder,
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

        $schema = SqliteSchemaPool::random($this->faker);
        $this->harness->setup($schema, $seed);

        try {
            $sql = $this->sqlBuilder->buildDelete($schema);

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
                throw new Error("ZTD DELETE failed after native success\nSeed: $seed\nSQL: $sql", 0, $e);
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
     * Reads the table on both sides and fails if they disagree.
     *
     * @param SchemaDefinition $schema The schema
     * @param int $seed The seed
     *
     * @throws Error
     */
    public function compareTableState(SchemaDefinition $schema, int $seed): void
    {
        $rawRows = $this->fetchAll($this->harness->getRawPdo(), $schema->name);

        $selectSql = sprintf('SELECT * FROM "%s"', str_replace('"', '""', $schema->name));
        $stmt = $this->harness->getZtdPdo()->query($selectSql);
        /** @var list<Row> $ztdRows */
        $ztdRows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        if (!$this->comparator->compareRows($rawRows, $ztdRows, $schema->primaryKeys)) {
            throw new Error(
                "DELETE table state mismatch\n" .
                "Seed: $seed\n" .
                "Schema: {$schema->name}\n" .
                'Raw row count: ' . count($rawRows) . "\n" .
                'ZTD row count: ' . count($ztdRows)
            );
        }
    }

    /**
     * Answers every row the connection reads.
     *
     * @param PDO $pdo The pdo
     * @param string $table Table it belongs to
     *
     * @return list<Row> What it answers
     */
    public function fetchAll(PDO $pdo, string $table): array
    {
        $stmt = $pdo->query(sprintf('SELECT * FROM "%s"', str_replace('"', '""', $table)));
        /** @var list<Row> $rows */
        $rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        return $rows;
    }
}
