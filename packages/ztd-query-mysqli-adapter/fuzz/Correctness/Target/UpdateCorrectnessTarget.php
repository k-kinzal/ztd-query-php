<?php

declare(strict_types=1);

namespace Fuzz\Correctness\Target;

use Error;
use Faker\Generator;
use Fuzz\Correctness\MysqliCorrectnessHarness;
use Fuzz\Correctness\ResultComparator;
use Fuzz\Correctness\SchemaAwareSqlBuilder;
use Fuzz\Correctness\SchemaDefinition;
use Fuzz\Correctness\SchemaPool;
use mysqli;
use mysqli_result;
use mysqli_sql_exception;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;

/**
 * @phpstan-import-type Row from StatementInterface
 */
final class UpdateCorrectnessTarget
{
    private MysqliCorrectnessHarness $harness;
    private ResultComparator $comparator;
    private SchemaAwareSqlBuilder $sqlBuilder;
    private Generator $faker;

    /**
     * Binds the instance to what it will work from.
     *
     * @param MysqliCorrectnessHarness $harness
     * @param SchemaAwareSqlBuilder $sqlBuilder
     * @param Generator $faker
     */
    public function __construct(
        MysqliCorrectnessHarness $harness,
        SchemaAwareSqlBuilder $sqlBuilder,
        Generator $faker
    ) {
        $this->harness = $harness;
        $this->comparator = new ResultComparator();
        $this->sqlBuilder = $sqlBuilder;
        $this->faker = $faker;
    }

    /**
     * __invoke.
     *
     * @param string $input
     */
    public function __invoke(string $input): void
    {
        $seed = crc32(str_pad($input, 4, "\0"));
        $this->faker->seed($seed);

        $schema = SchemaPool::random($this->faker);
        $this->harness->setup($schema, $seed);

        try {
            $sql = $this->sqlBuilder->buildUpdate($schema);

            $rawError = null;
            try {
                $this->harness->rawConnection()->query($sql);
            } catch (mysqli_sql_exception $e) {
                $rawError = $e;
            }

            try {
                $this->harness->getZtdMysqli()->query($sql);
            } catch (UnsupportedSqlException | UnknownSchemaException) {
                return;
            } catch (DatabaseException | mysqli_sql_exception) {
                return;
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
        $rawRows = $this->fetchAll($this->harness->getRawMysqli(), $schema->name);

        $result = $this->harness->getZtdMysqli()->query("SELECT * FROM `{$schema->name}`");
        /** @var list<Row> $ztdRows */
        $ztdRows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];

        if (!$this->comparator->compareRows($rawRows, $ztdRows, $schema->primaryKeys)) {
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
     * Answers every row the connection reads.
     *
     * @param mysqli $mysqli The mysqli
     * @param string $table Table it belongs to
     *
     * @return list<Row> What it answers
     */
    public function fetchAll(mysqli $mysqli, string $table): array
    {
        $result = $mysqli->query("SELECT * FROM `$table`");
        if (!$result instanceof mysqli_result) {
            return [];
        }
        /** @var list<Row> $rows */
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        return $rows;
    }
}
