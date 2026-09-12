<?php

declare(strict_types=1);

namespace Fuzz\Correctness;

use Error;
use mysqli_result;
use mysqli_sql_exception;
use ZtdQuery\Adapter\Mysqli\ZtdMysqliException;

/**
 * Compares native and ZTD SELECT rows or expected rejections.
 */
final class SelectResultOracle
{
    private ResultComparator $comparator;

    /**
     * Compare queries against the native and wrapped connections of one scenario.
     */
    public function __construct(private MysqliCorrectnessHarness $harness)
    {
        $this->comparator = new ResultComparator();
    }

    /**
     * Compare native and simulated SELECT results for the same generated SQL.
     *
     * @throws Error If native success differs from the simulated result.
     */
    public function compareSelect(string $sql, SchemaDefinition $schema, int $seed): void
    {

        $rawResult = null;
        $rawError = null;
        try {
            $result = $this->harness->getRawMysqli()->query($sql);
            if ($result instanceof mysqli_result) {

                $rawResult = $result->fetch_all(MYSQLI_ASSOC);
            }
        } catch (mysqli_sql_exception $e) {
            $rawError = $e;
        }

        $ztdResult = null;
        $ztdError = null;
        try {
            $result = $this->harness->getZtdMysqli()->query($sql);
            if ($result instanceof mysqli_result) {

                $ztdResult = $result->fetch_all(MYSQLI_ASSOC);
            }
        } catch (ZtdMysqliException $e) {
            if ($rawError !== null) {
                return;
            }
            throw new Error("ZTD SELECT failed after native success\nSeed: $seed\nSQL: $sql", 0, $e);
        } catch (mysqli_sql_exception $e) {
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
            $hasOrderBy = stripos($sql, 'ORDER BY') !== false;
            if (!$this->comparator->compareRows($rawResult, $ztdResult, $schema->primaryKeys, [], !$hasOrderBy)) {
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
