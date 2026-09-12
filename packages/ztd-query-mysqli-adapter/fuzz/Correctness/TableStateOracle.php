<?php

declare(strict_types=1);

namespace Fuzz\Correctness;

use Error;
use mysqli_result;

/**
 * Compares the complete native and simulated table after a generated mutation.
 */
final class TableStateOracle
{
    /**
     * Use the two isolated connections of the active scenario.
     */
    public function __construct(private MysqliCorrectnessHarness $harness)
    {
    }

    /**
     * Compare row values and primary-key ordering after the same operation.
     *
     * @throws Error When the native and simulated table states differ.
     */
    public function compare(SchemaDefinition $schema, int $seed, string $sql): void
    {
        $raw = $this->harness->getRawMysqli()->query("SELECT * FROM `{$schema->name}`");
        $ztd = $this->harness->getZtdMysqli()->query("SELECT * FROM `{$schema->name}`");
        if (!$raw instanceof mysqli_result || !$ztd instanceof mysqli_result) {
            throw new Error("Table snapshot failed\nSeed: $seed\nSQL: $sql");
        }
        $rawRows = $raw->fetch_all(MYSQLI_ASSOC);
        $ztdRows = $ztd->fetch_all(MYSQLI_ASSOC);
        $raw->free();
        $ztd->free();
        if (!(new ResultComparator())->compareRows($rawRows, $ztdRows, $schema->primaryKeys)) {
            throw new Error("Table state mismatch\nSeed: $seed\nSQL: $sql\n" . var_export([$rawRows, $ztdRows], true));
        }
    }
}
