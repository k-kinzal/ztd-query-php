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
final class SelectCorrectnessTarget
{
    private PgCorrectnessHarness $harness;
    private ResultComparator $comparator;
    private PgSchemaAwareSqlBuilder $sqlBuilder;
    private Generator $faker;

    /**
     * Binds the instance to what it will work from.
     *
     * @param PgCorrectnessHarness $harness
     * @param PgSchemaAwareSqlBuilder $sqlBuilder
     * @param Generator $faker
     */
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
     * __invoke.
     *
     * @param string $input
     */
    public function __invoke(string $input): void
    {
        $seed = crc32(str_pad($input, 4, "\0"));
        $this->faker->seed($seed);

        $schema = PgSchemaPool::random($this->faker);
        $this->harness->setup($schema, $seed);
        $joinSchema = new SchemaDefinition(
            '_ztd_join_no_pk',
            'CREATE TABLE "_ztd_join_no_pk" (item_id INTEGER NOT NULL, tag VARCHAR(50) NOT NULL)',
            ['item_id', 'tag'],
            [],
        );

        try {
            $this->setupJoinTable($joinSchema);
            $this->compareSelect($this->sqlBuilder->buildJoinSelect($schema, $joinSchema), $schema, $seed);

            $queryCount = $this->faker->numberBetween(1, 5);
            for ($i = 0; $i < $queryCount; $i++) {
                $sql = $this->sqlBuilder->buildSelect($schema);
                $this->compareSelect($sql, $schema, $seed);
            }
        } finally {
            $this->harness->getRawPdo()->exec('DROP TABLE IF EXISTS "_ztd_join_no_pk" CASCADE');
            $this->harness->teardown();
        }
    }

    private function setupJoinTable(SchemaDefinition $schema): void
    {
        $this->harness->getRawPdo()->exec('DROP TABLE IF EXISTS "_ztd_join_no_pk" CASCADE');
        $this->harness->getRawPdo()->exec($schema->sql);
        $this->harness->getRawPdo()->exec(
            "INSERT INTO \"_ztd_join_no_pk\" VALUES (1, 'new'), (1, 'sale'), (2, 'blue'), (99, 'orphan')",
        );

        $this->harness->getZtdPdo()->exec($schema->sql);
        $this->harness->getZtdPdo()->exec(
            "INSERT INTO \"_ztd_join_no_pk\" VALUES (1, 'new'), (1, 'sale'), (2, 'blue'), (99, 'orphan')",
        );
    }

    /**
     * @throws Error
     */
    private function compareSelect(string $sql, SchemaDefinition $schema, int $seed): void
    {
        /** @var list<Row>|null $rawResult */
        $rawResult = null;
        $rawError = null;
        try {
            $stmt = $this->harness->getRawPdo()->query($sql);
            $rawResult = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : null;
        } catch (PDOException $e) {
            $rawError = $e;
        }

        /** @var list<Row>|null $ztdResult */
        $ztdResult = null;
        $ztdError = null;
        try {
            $stmt = $this->harness->getZtdPdo()->query($sql);
            $ztdResult = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : null;
        } catch (UnsupportedSqlException | UnknownSchemaException | DatabaseException $e) {
            if ($rawError !== null) {
                return;
            }
            throw new Error("ZTD SELECT failed after native success\nSeed: $seed\nSQL: $sql", 0, $e);
        } catch (PDOException $e) {
            $ztdError = $e;
        }

        if ($rawError !== null && $ztdError !== null) {
            return;
        }

        if ($rawError !== null) {
            return;
        }
        if ($ztdError !== null) {
            throw new Error("ZTD SELECT failed after native success\nSeed: $seed\nSQL: $sql", 0, $ztdError);
        }

        if ($rawResult !== null && $ztdResult !== null) {
            /** @var list<Row> $rawResult */
            /** @var list<Row> $ztdResult */
            $hasOrderBy = stripos($sql, 'ORDER BY') !== false;
            if (!$this->comparator->compareRows($rawResult, $ztdResult, $schema->primaryKeys, $schema->columnTypes, !$hasOrderBy)) {
                throw new Error(
                    "SELECT result mismatch\n" .
                    "Seed: $seed\n" .
                    "SQL: $sql\n" .
                    "Schema: {$schema->name}\n" .
                    'Raw result count: ' . count($rawResult) . "\n" .
                    'ZTD result count: ' . count($ztdResult) . "\n" .
                    'Raw first row: ' . json_encode($rawResult[0] ?? null) . "\n" .
                    'ZTD first row: ' . json_encode($ztdResult[0] ?? null)
                );
            }
        }
    }
}
