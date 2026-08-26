<?php

declare(strict_types=1);

namespace Fuzz\Correctness\Target;

use Error;
use Faker\Generator;
use Fuzz\Correctness\MysqliCorrectnessHarness;
use Fuzz\Correctness\ResultComparator;
use Fuzz\Correctness\SchemaDefinition;
use Fuzz\Correctness\SchemaPool;
use mysqli;
use mysqli_result;
use Throwable;
use ZtdQuery\Connection\StatementInterface;

/**
 * @phpstan-import-type Row from StatementInterface
 */
final class ReplaceCorrectnessTarget
{
    public function __construct(
        private readonly MysqliCorrectnessHarness $harness,
        private readonly Generator $faker,
        private readonly ResultComparator $comparator = new ResultComparator(),
    ) {
    }

    /**
     * @throws Error
     */
    public function __invoke(string $input): void
    {
        $seed = crc32(str_pad($input, 4, "\0"));
        $this->faker->seed($seed);
        $schema = SchemaPool::random($this->faker);
        if ($schema->primaryKeys === []) {
            return;
        }

        $fixtures = $this->harness->setup($schema, $seed);
        $row = $fixtures[$seed % count($fixtures)];
        $columns = array_keys($row);
        $sql = sprintf(
            'REPLACE INTO `%s` (%s) VALUES (%s)',
            $schema->name,
            implode(', ', array_map(static fn (string $column): string => "`$column`", $columns)),
            implode(', ', array_fill(0, count($columns), '?')),
        );
        $params = array_values($row);

        try {
            $this->harness->getRawMysqli()->execute_query($sql, $params);
            try {
                $this->harness->getZtdMysqli()->execute_query($sql, $params);
            } catch (Throwable $exception) {
                throw new Error("ZTD prepared REPLACE failed after native success\nSeed: $seed\nSQL: $sql", 0, $exception);
            }

            $this->compareTableState($schema, $seed, $sql);
        } finally {
            $this->harness->teardown();
        }
    }

    /**
     * @throws Error
     */
    private function compareTableState(SchemaDefinition $schema, int $seed, string $sql): void
    {
        $rawRows = $this->fetchAll($this->harness->getRawMysqli(), $schema->name);
        $ztdRows = $this->fetchAll($this->harness->getZtdMysqli(), $schema->name);

        if (!$this->comparator->compareRows($rawRows, $ztdRows, $schema->primaryKeys)) {
            throw new Error(
                "Prepared REPLACE table state mismatch\n"
                . "Seed: $seed\n"
                . "SQL: $sql\n"
                . 'Native: ' . json_encode($rawRows, JSON_THROW_ON_ERROR) . "\n"
                . 'ZTD: ' . json_encode($ztdRows, JSON_THROW_ON_ERROR),
            );
        }
    }

    /** @return list<Row> */
    private function fetchAll(mysqli $mysqli, string $table): array
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
